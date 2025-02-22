<?php

use SIUToba\rest\seguridad\autenticacion;
use SIUToba\rest\seguridad\autenticacion\autenticacion_basic_http;
use SIUToba\rest\seguridad\autenticacion\oauth2\oauth_token_decoder_web;
use SIUToba\rest\seguridad\autenticacion\usuarios_usuario_password;
use SIUToba\rest\seguridad\autorizacion\autorizacion_scopes;

/**
 * Clase que instancia la libreria necesaria para atender un pedido REST
 * @package Centrales
 */
class toba_rest
{
	const CARPETA_REST = "/rest";
	protected $conf_ini;
	protected $app;

	static function url_rest($api='')
	{
		$url = toba_recurso::url_proyecto() . self::CARPETA_REST;
		if (trim($api) != '') $url .= "/$api";
		
		return $url; 
	}

	static function url_api_doc($api='')
	{
		return toba_http::get_protocolo() . toba_http::get_nombre_servidor() . self::url_rest($api) . '/api-docs';
	}


	function conf__inicial($api='')
	{
		if (! $this->es_pedido_documentacion($api)) {
			$this->app = $this->instanciar_libreria_rest($api);
			$this->configurar_libreria_rest($this->app);
		}
	}

	function get_instancia_rest()
	{
		return $this->app;
	}

	function ejecutar($api='')
	{
		if ($this->es_pedido_documentacion($api)) {
			$this->rederigir_a_swagger($api);
			return;
		}

		$this->app->procesar();
	}


	/**
	 * @return SIUToba\rest\rest
	 */
	public function instanciar_libreria_rest($api='')
	{
		$ini = $this->get_conf($api);
		$es_produccion = (boolean) toba::instalacion()->es_produccion();

		$path_controladores = $this->get_path_controladores($api);
		$url_base = self::url_rest($api);

		$settings = array(
			'path_controladores' => $path_controladores,
			'url_api' => $url_base,
			'prefijo_api_docs' => 'api-docs',
			'debug' => !$es_produccion,
			'encoding' => 'latin1'
		);		
		$datos_ini_proyecto		=	$this->get_ini_proyecto();
		$datos_api_major_minor	=	$this->get_param_major_minor($api, $datos_ini_proyecto);    //Hay que extraer alguna config puntual de api

		//Busco version del proyecto para recurso info
		if (! isset($datos_ini_proyecto['proyecto']['version'])) {
			throw new toba_error('No esta especificada la version del sistema');
		} else {
			$settings['version'] = $datos_ini_proyecto['proyecto']['version'];
		}		

		//Busca id del proyecto para mejorar el titulo de la documentacion
        if (!empty($datos_ini_proyecto) && isset($datos_ini_proyecto['proyecto']['id'])) {
			$settings['api_titulo'] = 'Referencia de API para ' . $datos_ini_proyecto['proyecto']['id'];
		}
		
		$settings = array_merge($settings, $ini->get('settings', null, array(), false), $datos_api_major_minor);
		$app = new SIUToba\rest\rest($settings);
		return $app;
	}


	/**
	 * Configurar la libreria de rest, seteando las dependencias o configuracion que permite la misma
	 * @param $app
	 * @throws toba_error_modelo si hay errores de configuracion
	 */
	public function configurar_libreria_rest($app)
	{
		$app->container->singleton('logger', function () {
			return new toba_rest_logger();
		});

		$autenticaciones = $this->get_metodos_autenticacion();
		$modelo_proyecto = $this->get_modelo_proyecto();

		$metodos = array();
		foreach ($autenticaciones as $autenticacion) {
			switch($autenticacion) {
				case 'basic':
					$passwords = new toba_usuarios_rest_conf($modelo_proyecto);
					$metodos[] =  new autenticacion\autenticacion_basic_http($passwords);
					break;
				case 'digest':
					$passwords = new toba_usuarios_rest_conf($modelo_proyecto);
					$metodos[] = new autenticacion\autenticacion_digest_http($passwords);
					break;
				case 'api_key':
					$passwords = new toba_usuarios_rest_conf($modelo_proyecto);
					$metodos[] = new autenticacion\autenticacion_api_key($passwords);
					break;
				case 'ssl':
					$certificados = new toba_usuarios_rest_ssl($modelo_proyecto);
					$metodos[] = new autenticacion\autenticacion_ssl($certificados);
					break;
				case 'jwt':
					$certificados = new toba_usuarios_rest_jwt($modelo_proyecto);
					$metodos[] = new autenticacion\autenticacion_jwt($certificados);
					break;
				case 'oauth2':
					$conf = $this->get_conf();
					$conf_auth = $conf->get('oauth2');
					$metodos[] = $this->get_autenticador_oauth($conf_auth);
					//Le inyecto el autorizador al app
					$app->set_autorizador($this->get_autorizador_oauth($conf_auth));
					break;
				case 'toba':
					$toba_aut = new toba_autenticacion_basica();
					$user_prov = new toba_usuarios_rest_bd($toba_aut);
					$metodos[] = new autenticacion\autenticacion_basic_http($user_prov);
					break;
				default:
					throw new toba_error_modelo("Debe especificar un tipo de autenticacion valido [digest, basic] en el campo 'autenticacion'");
			}
		}
		$app->container->singleton('autenticador', function () use ($metodos) {
			return $metodos;
		});

		$app->container->singleton('rest_quoter', function () {
			return toba::db();
		});
	}

	protected function get_metodos_autenticacion()
	{
		$conf = $this->get_conf();
		$autenticaciones = explode(',', str_replace(' ', '', $conf->get('autenticacion', null, 'basic')));

		// jwt y oauth usan el mismo header
		if (in_array('jwt', $autenticaciones) && in_array('oauth', $autenticaciones)){
			throw new toba_error_modelo("No se puede especificar en simultaneo el tipo de autenticacion 'jwt' y 'oauth' en el campo 'autenticacion'");
		}

		// basic y digest, se procesan al final y hacen redirect para pedir datos
		if (in_array('digest', $autenticaciones) && in_array('basic', $autenticaciones)){
			throw new toba_error_modelo("No se puede especificar en simultaneo el tipo de autenticacion 'digest' y 'basic ' en el campo 'autenticacion'");
		}

		// hay que priorizar, basic y digest (si existe alguno) hacen redirect primero
		$order = array('ssl', 'jwt', 'api_key', 'toba', 'digest', 'basic');
		$autenticaciones = array_intersect($order, $autenticaciones);

		return $autenticaciones;
	}

	protected function get_conf($api='')
	{
		if (!isset($this->conf_ini)) {
			$archivo = toba_modelo_rest::get_path_archivo($this->get_modelo_proyecto(), toba_modelo_rest::TIPO_SERVER, $api);
			toba::config()->add_config_file('rest_servidor', $archivo);
			toba::config()->load();
			
			//Devuelve ini para mantener contrato por ahora
			$this->conf_ini = new toba_ini();
			$this->conf_ini->set_entradas(toba::config()->get_seccion('rest_servidor'));
		}
		return $this->conf_ini;
	}

	protected function rederigir_a_swagger($api)
	{
		$swagger_ui = toba_recurso::url_toba() . '/swagger/index.html';
		$proy = toba_rest::url_api_doc($api);
		header('Location: ' . $swagger_ui . '?url=' . $proy);
	}

	/**
	 * @return string
	 */
	protected function get_path_controladores($api='')
	{
		$api_base = toba_proyecto::get_path_php() . self::CARPETA_REST;
		$api_pers = toba_proyecto::get_path_pers_php() . self::CARPETA_REST;
		
		$ini_server = $this->get_conf($api);
		$path_controladores = array(
					$ini_server->get($api, 'path_api', $api_base, FALSE),
					$ini_server->get($api, 'path_api_pers', $api_pers, FALSE)
		);
		
		return $path_controladores;
	}

	/**
	 * @return bool
	 */
	public function es_pedido_documentacion($api='')
	{
            if (! is_null($this->app)){
                return $this->get_instancia_rest()->config('url_api') == rtrim( $_SERVER['REQUEST_URI'], '/');
            }
            return self::url_rest($api) == rtrim( $_SERVER['REQUEST_URI'], '/');
	}

	protected function get_modelo_proyecto()
	{
		if (!isset($this->modelo_proyecto)) {
			$catalogo = toba_modelo_catalogo::instanciacion();
			$id_instancia = toba::instancia()->get_id();
			$id_proyecto = toba::proyecto()->get_id();
			$this->modelo_proyecto = $catalogo->get_proyecto($id_instancia, $id_proyecto);
		}
		return $this->modelo_proyecto;
	}

	protected function get_ini_proyecto()
	{
		$resultado = array();
		if (toba::config()->existe_valor('proyecto', 'proyecto', 'id')) {
			$resultado = toba::config()->get_seccion('proyecto');
		}
		return $resultado;
	}

	protected function  get_autenticador_oauth($conf_auth)
	{
		$decoder = null;
		switch ($conf_auth['decodificador_tokens']) {
			case 'local':
				die('not implemented');
				break;
			case 'web':
				$cliente = new \GuzzleHttp\Client(array('base_url' => $conf_auth['endpoint_decodificador_url']));
				$decoder = new oauth_token_decoder_web($cliente);
				$decoder->set_cache_manager(new \Doctrine\Common\Cache\ApcCache());
				$decoder->set_tokeninfo_translation_helper(new autenticacion\oauth2\tokeninfo_translation_helper_arai());
				break;
		}

		$auth = new autenticacion\autenticacion_oauth2();
		$auth->set_decoder($decoder);
		return $auth;
	}

	protected function get_autorizador_oauth($conf_auth)
	{
		if (!isset($conf_auth['scopes'])) {
			die("es necesario definir el parámetro 'scopes' en el bloque oauth2 de la configuración");
		}
		$auth = new autorizacion_scopes();
		$auth->set_scopes_requeridos(array_map('trim', explode(',', $conf_auth['scopes'])));
		return $auth;
	}

	protected function get_param_major_minor($api, $datos_ini_proyecto = null)
	{
		//Si no existe definición (api_major:api_minor) en proyecto.ini devuelve un error
		if (!isset($datos_ini_proyecto['proyecto']['api_major']) && !isset($datos_ini_proyecto['api_' . $api]['api_major'])) {
			throw new toba_error('No esta especificada la version de la API (major:minor)');
		}

		//Si es < 0, api_nombre es una version mayor a la declarada en proyecto.ini, no existe dicha versión
		if (strcmp("v{$datos_ini_proyecto['proyecto']['api_major']}", $api) < 0) {
			throw new toba_error('No esta especificada la version de la API (api_major:api_minor)');
		}

		$datos_api	= [];
		//Si no existen subconjuntos api_<version> utilizó (api_major:api_minor) de [proyecto] proyecto.ini
		if (isset($datos_ini_proyecto['proyecto']['api_major']) && isset($datos_ini_proyecto['proyecto']['api_minor'])) { 
            //Seteo el api_version con los valores del subconjunto (api_major:api_minor)
			$datos_api = [
                'api_version' => "v{$datos_ini_proyecto['proyecto']['api_major']}.{$datos_ini_proyecto['proyecto']['api_minor']}",
                'api_major' => $datos_ini_proyecto['proyecto']['api_major'],
                'api_minor' => $datos_ini_proyecto['proyecto']['api_minor']
            ];
		}

		//Si existen subconjuntos api_<version> utilizó los siguientes parámetros
		$indx = 'api_' . $api;
		if (isset($datos_ini_proyecto[$indx]['api_major']) && isset($datos_ini_proyecto[$indx]['api_minor'])) {
            $datos_api = [
                'api_version' => "v{$datos_ini_proyecto[$indx]['api_major']}.{$datos_ini_proyecto[$indx]['api_minor']}",
                'api_major' => $datos_ini_proyecto[$indx]['api_major'],
                'api_minor' => $datos_ini_proyecto[$indx]['api_minor']
            ];
		}

		return $datos_api;
	}
}
