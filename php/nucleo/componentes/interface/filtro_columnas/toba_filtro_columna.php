<?php

/**
 * Contiene una condicion y un ef. Se trata de reutilizar al maximo la logica de los efs sin heredarlos, es por eso que muchas llamadas pasan directo
 *
 * @package Componentes
 * @subpackage Filtro
 **/
abstract class toba_filtro_columna
{
	protected $_datos;
	protected $_ef;
	protected $_padre;
	protected $_id_form_cond;
	protected $_estado = null;	
	protected $_condiciones = array();
	protected $_solo_lectura = false;
	protected $_funcion_formateo = null;
	protected $_condicion_default = null;
	
	/**
	 * Constructor
	 * @param array $datos
	 * @param toba_ei $padre
	 */
	function __construct($datos, $padre) 
	{
		$this->_datos = $datos;
		$this->_padre = $padre;
		$this->_id_form_cond = "col_" . $this->_padre->get_id_form() . $this->_datos['nombre'];		
		$this->ini();
	}
	
	/**
	 * M�todo para construir el ef adecuado seg�n el tipo de columna
	 */
	abstract function ini();

	//-----------------------------------------------
	//--- COMANDOS ---------------------------------
	//-----------------------------------------------	
	/**
	 * Indica el estado de la columna
	 * @param array $estado Array( 'condicion' => 'xx', 'valor' => yy)
	 * @throws toba_error_def
	 */
	function set_estado($estado)
	{
		if ($this->hay_condicion_fija()){
			if  (isset($estado['condicion']) && isset($this->_estado)  &&($this->_estado['condicion'] != $estado['condicion'])){	//Si la condicion no viene seteada retorna al default
				$msg = "Existe una condici�n fija para la columna '".$this->get_nombre().
							"' la misma no se puede cambiar seteando el estado.";
				toba_logger::instancia()->error($msg);
				throw new toba_error_def('Existe una condici�n fija para la columna, la misma no se puede cambiar seteando el estado. Revise el log');
			}
		}

		$this->_estado = $estado;
		$this->_ef->set_estado($estado['valor']);
	}	
	
	/**
	 * Indica si la columna es visible inicialmente
	 * @param boolean $visible
	 */
	function set_visible($visible)
	{
		$this->_datos['inicial'] = $visible;
	}	
	
	/**
	 * Indica si la columna debe colocarse solo lectura
	 * @param boolean $solo_lectura
	 */
	function set_solo_lectura($solo_lectura = true)
	{
		$this->_solo_lectura = $solo_lectura;
		$this->_ef->set_solo_lectura($solo_lectura);
	}
	
	/**
	 * Indica la expresion de evaluacion de la columna
	 * @param string $campo
	 */
	function set_expresion($campo)
	{
		$this->_datos['expresion'] = $campo;
	}
	
	/**
	 * Carga el estado de la columna
	 * @throws toba_error_seguridad
	 */
	function cargar_estado_post()
	{
		$this->_estado = array();	
		if (isset($_POST[$this->_id_form_cond])) {
			$condicion = $_POST[$this->_id_form_cond];
			if (! isset($this->_condiciones[$condicion])) {
				toba_logger::instancia()->error("La condici�n '$condicion' no es una condici�n v�lida");
				throw new toba_error_seguridad("La condici�n indicada no es v�lida");
			}
			$this->_estado['condicion'] = $condicion;
		} else {
			throw new toba_error_seguridad("No hay una condici�n valida");
		}

		$this->_ef->cargar_estado_post();			
		$this->_estado['valor'] = $this->_ef->get_estado();
		
	}	
	
	/**
	 * Agrega una condici�n a la columna
	 * @param mixed $id
	 * @param toba_filtro_condicion $condicion
	 */
	function agregar_condicion($id, toba_filtro_condicion $condicion)
	{
		$this->_condiciones[$id] = $condicion;
	}
	
	/**
	 * Elimina la condicion indicada
	 * @param mixed $id
	 */
	function borrar_condicion($id)
	{
		unset($this->_condiciones[$id]);
	}

	/**
	 * Indica con que funcion se formateara el dato
	 * @param mixed $funcion
	 */
	function set_formateo($funcion)
	{
		$this->_funcion_formateo = $funcion;
	}
	//-----------------------------------------------
	//--- CONSULTAS ---------------------------------
	//-----------------------------------------------
	
	/**
	 * Indica el id de la columna 
	 * @return mixed
	 */
	function get_id_metadato()
	{
		return $this->_datos['objeto_ei_filtro_col'];
	}
	
	/**
	 * Indica el id del filtro que contiene la columna
	 * @return mixed
	 */
	function get_id_form()
	{
		return $this->_padre->get_id_form();
	}
	
	/**
	 * Indica la posicion de la columna con respecto a las otras
	 * @return integer
	 */
	function get_tab_index()
	{
		return $this->_padre->get_tab_index();
	}
	
	/**
	 * Indica si la columna es obligatoria
	 * @return boolean
	 */
	function es_obligatorio()
	{
		return $this->_ef->es_obligatorio();
	}
	
	/**
	 * Indica si la columna es solo lectura
	 * @return boolean
	 */
	function es_solo_lectura()
	{
		return $this->_solo_lectura;
	}
	
	/**
	 * Indica si la columna es visible
	 * @return boolean
	 */
	function es_visible()
	{
		return $this->_datos['inicial'];
	}
	
	/**
	 * Indica si la columna es compuesta, esto es si el dato es complejo
	 * @return boolean
	 */
	function es_compuesto()
	{
		return false;
	}
	
	/**
	 * Devuelve el nombre de la columna
	 * @return string
	 */
	function get_nombre()
	{
		return $this->_datos['nombre'];
	}
	
	/**
	 * Devuelve un objeto de tipo ef
	 * @return toba_ef
	 */
	function get_ef()
	{
		return $this->_ef;
	}
	
	/**
	 * Devuelve la expresion de la columna
	 * @return string
	 */
	function get_expresion()
	{
		return $this->_datos['expresion'];
	}

	/**
	 * Devuelve la etiqueta de la columna
	 * @return string
	 */
	function get_etiqueta()
	{
		return $this->_datos['etiqueta'];
	}

	/**
	 * Devuelve la funcion de formateo de la columna
	 * @return mixed
	 */
	function get_formateo()
	{
		return $this->_funcion_formateo;	
	}
	
	/**
	 * Invoca la validacion del estado de la columna
	 * @return boolean
	 */
	function validar_estado()
	{
		return $this->_ef->validar_estado();
	}
	
	/**
	 * Resetea el estado de la columna
	 */
	function resetear_estado()
	{
		$this->_ef->resetear_estado();
		$this->_estado = null;
	}
	
	/**
	 * Devuelve el estado de la columna
	 * @return mixed
	 */
	function get_estado()
	{
		return $this->_estado;
	}
	
	/**
	 * Indica si la columna tiene estado
	 * @return boolean
	 */
	function tiene_estado()
	{
		return isset($this->_estado);
	}
	
	/**
	 * Indica la cantidad de condiciones de la columna
	 * @return integer
	 */
	function get_cant_condiciones()
	{
		return count($this->_condiciones);
	}

	/**
	 * Permite saber si la columna tiene una condicion fija o no.
	 * @return boolean
	 */
	function hay_condicion_fija()
	{
		$hay_fija = false;
		foreach($this->_condiciones as $condicion){
			if ($condicion->es_condicion_fija()){
				$hay_fija = true;
				break;
			}
		}
		return $hay_fija;
	}

	/**
	 * Coloca una condicion como fija para esta columna, la condicion permanecera solo_lectura y se
	 * transformara en default para esta columna. El estado decide si esta seteada o no.
	 * @param string $nombre
	 * @param boolean $estado
	 */
	function set_condicion_fija($nombre, $estado = true)
	{
		if (!isset($this->_condiciones[$nombre])){
			toba_logger::instancia()->error("No existe la condici�n '$nombre' para la columna '". $this->get_nombre()."'");
			throw new toba_error_def('No existe la condici�n se�alada para la columna indicada, revise el log');
		}

		if ($this->hay_condicion_fija()){
			toba_logger::instancia()->error("Ya existe una condici�n fija para la columna '".$this->get_nombre()."'");
			throw new toba_error_def('Ya existe una condici�n fija para la columna indicada, revise el log');
		}

		$this->_condicion_default = ($estado) ? $nombre : null;		//Si el estado es false se limpia el default
		$this->condicion($nombre)->set_condicion_fija($estado);
	}

	/**
	 * Setea una condicion como default para la columna, esto es, cuando no haya estado especificado
	 * se tomara la condicion default para la columna
	 * @param string $nombre
	 */
	function set_condicion_default($nombre)
	{
		if (!isset($this->_condiciones[$nombre])){
			toba_logger::instancia()->error("No existe la condici�n '$nombre' para la columna '". $this->get_nombre()."'");
			throw new toba_error_def('No existe la condici�n se�alada para la columna indicada, revise el log');
		}
		$this->_condicion_default = $nombre;
	}

	/**
	 *  Elimina la condicion default para la columna
	 */
	function eliminar_condicion_default()
	{
		$this->_condicion_default = null;
	}

	/**
	 * Determina si la columna tiene condicion default o no.
	 * @return boolean
	 */
	function hay_condicion_default()
	{
		return (! is_null($this->_condicion_default));
	}

	/**
	 * Retorna una condici�n asociada a la columna, por defecto la que actualmente selecciono el usuario
	 * @return toba_filtro_condicion
	 */
	function condicion($nombre = null)
	{
		if (! isset($nombre)) {
			if (isset($this->_estado)) {
				return $this->_condiciones[$this->_estado['condicion']];
			} else {
				toba_logger::instancia()->error("No hay una condici�n actualmente seleccionada para la columna '".$this->get_nombre()."'");
				throw new toba_error_def('No hay una condici�n actualmente seleccionada para la columna indicada, revise el log');
			}
		} else {
			return $this->_condiciones[$nombre];
		}
	}
	
	/**
	 * Fija una condicion 
	 * @param toba_filtro_condicion $condicion
	 * @param string $nombre
	 * @throws toba_error_def
	 */
	function set_condicion(toba_filtro_condicion $condicion, $nombre=null)
	{
		if (! isset($nombre)) {
			if (isset($this->_estado)) {
				$this->_condiciones[$this->_estado['condicion']] = $condicion;
			} else {
				toba_logger::instancia()->error("No hay una condici�n actualmente seleccionada para la columna '".$this->get_nombre()."'");
				throw new toba_error_def('No hay una condici�n actualmente seleccionada para la columna indicada, revise el log');
			}
		} else {
			$this->_condiciones[$nombre] = $condicion;
		}		
	}
	
	/**
	 * Devuelve una clausula SQL en base a su estado interno
	 * @return string
	 */
	function get_sql_where()
	{
		if (isset($this->_estado)) {
			$id = $this->_estado['condicion'];	
			return $this->_condiciones[$id]->get_sql($this->get_expresion(), $this->_estado['valor']);
		}
	}


	//-----------------------------------------------
	//--- SALIDA HTML  ------------------------------
	//-----------------------------------------------
	/**
	 * Genera el HTML para graficar la condicion
	 * @return string
	 */
	function get_html_condicion()
	{
		$class = toba::output()->get('FiltroColumnas')->getClassCss();
		if (count($this->_condiciones) > 1) {
			//-- Si tiene mas de una condicion se muestran con un combo
			$onchange = $this->get_objeto_js(). '.cambio_condicion("' . $this->get_nombre().'");';
			$html = '';
			if ($this->hay_condicion_default() && (!isset($this->_estado['condicion']) || is_null($this->_estado['condicion']))){
				//Si no tiene estado y hay default seteado, el default es el nuevo estado
				$this->_estado['condicion'] = $this->_condicion_default;
			}
			if ($this->_solo_lectura || $this->hay_condicion_fija()) {
				$id = $this->_id_form_cond.'_disabled';
				$disabled = 'disabled';
				$html .= "<input class='$class' type='hidden' id='{$this->_id_form_cond}' name='{$this->_id_form_cond}' value='{$this->_estado['condicion']}'/>\n";				
			} else {
				$disabled = '';
				$id = $this->_id_form_cond;
			}
			$html .= "<select class='$class' id='$id' name='$id' $disabled onchange='$onchange'>";
			foreach ($this->_condiciones as $id => $condicion) {
				$selected = '';
				if (isset($this->_estado) && $this->_estado['condicion'] == $id) {
					$selected = 'selected';	
				}
				$html .= "<option value='$id' $selected>".$condicion->get_etiqueta()."</option>\n";
			}
			$html .= '</select>';

			return $html;
		} else {
			reset($this->_condiciones);
			$condicion = key($this->_condiciones);
			//-- Si tiene una unica, seria redundante mostrarle la unica opci�n, se pone un hidden
			return "<input type='hidden' id='{$this->_id_form_cond}' name='{$this->_id_form_cond}' value='$condicion'/>&nbsp;";
		}
	}	
	
	/**
	 * Genera el HTML para el campo
	 */
	function get_html_valor()
	{
		echo $this->_ef->get_input();
	}

	/**
	 * Genera la etiqueta
	 * @return string
	 */
	function get_html_etiqueta()
	{
		$html = '';
		$marca ='';		
		if ($this->_ef->es_obligatorio()) {
			$estilo = 'ei-filtro-etiq-oblig';
			$marca = '(*)';
		} else {
			$estilo = 'ei-filtro-etiq opcional';
		}
		$desc='';
		$desc = $this->_datos['descripcion'];
		if ($desc !=""){
			$desc = toba_parser_ayuda::parsear($desc);
			$desc =  toba::output()->get('Filtro')->getIconoAyuda($desc);			
		}
		$id_ef = $this->_ef->get_id_form();					
		$editor = '';		
		//$editor = $this->generar_vinculo_editor($ef);
		$etiqueta = $this->get_etiqueta();
		$html .= "<label for='$id_ef' class='col-sm-2 control-label $estilo'>$editor $desc $etiqueta $marca</label>\n";
		return $html;
	}
		
	function get_pdf_valor()
	{
		$valor = $this->_ef->get_descripcion_estado('pdf');
		return $valor;
	}
	
	function get_excel_valor()
	{
		$valor = $this->_ef->get_descripcion_estado('excel');
		return $valor;		
	}
	
	//-----------------------------------------------
	//--- JAVASCRIPT   ------------------------------
	//-----------------------------------------------
	/**
	 * Devuelve el objeto JS correspondiente a la columna
	 * @param string $id
	 * @return mixed
	 */
	function get_objeto_js_ef($id)
	{
		return $this->_padre->get_objeto_js_ef($id);
	}
	
	/**
	 * Devuelve el objeto JS correspondiente al filtro
	 * @return type
	 */	
	function get_objeto_js()
	{
		return $this->_padre->get_objeto_js();
	}
		
	/**
	 * Devuelve el consumo JS de la columna
	 * @return string
	 */
	function get_consumo_javascript()
	{
		return $this->_ef->get_consumo_javascript();
	}
	
	/**
	 * Genera el objeto JS para la columna y lo devuelve
	 * @return mixed
	 */
	function crear_objeto_js()
	{
		return $this->_ef->crear_objeto_js();
	}	
}

?>