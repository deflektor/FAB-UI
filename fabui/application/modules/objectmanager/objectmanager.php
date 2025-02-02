<?php

class Objectmanager extends Module {

	public function __construct() {
		parent::__construct();

		$this -> load -> helper('print_helper');
		/** IF PRINTER IS BUSY I CANT CHANGE SETTINGS  */
		if (is_printer_busy()) {
			//redirect('dashboard');
			$this -> layout -> set_printer_busy(true);
		}

		$this -> lang -> load($_SESSION['language']['name'], $_SESSION['language']['name']);

		if (file_exists('download')) {
			shell_exec('sudo rm -r download');
		}

	}

	public function index() {

		//carico X class database
		$this -> load -> database();
		$this -> load -> model('objects');
		$this -> load -> model('tasks');

		/** CHECK IF THERE IS AN OPEN TASK */
		$_task = $_task = $this -> tasks -> get_running('objectmanager');
		if ($_task) {

			$_task_attributes = json_decode($_task['attributes'], true);

			switch($_task['type']) {

				case 'slice' :
					//$this->g_code($_task_attributes['id_object'], $_task_attributes['id_file']);
					redirect('/objectmanager/prepare/stl/' . $_task_attributes['id_object'] . '/' . $_task_attributes['id_file']);
					break;
				case 'meshlab' :
					redirect('/objectmanager/prepare/asc/' . $_task_attributes['id_object'] . '/' . $_task_attributes['id_file']);
					break;
			}
		}

		//carico helpers
		$this -> load -> helper('ft_file_helper');
		$this -> load -> helper('smart_admin_helper');
		$this -> load -> helper('ft_date_helper');

		//$this -> layout -> add_js_file(array('src' => 'application/layout/assets/js/plugin/easy-pie-chart/jquery.easy-pie-chart.min.js', 'comment' => ''));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/datatables/jquery.dataTables.min.js', 'comment' => ''));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/datatables/dataTables.colVis.min.js', 'comment' => ''));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/datatables/dataTables.tableTools.min.js', 'comment' => ''));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/datatables/dataTables.bootstrap.min.js', 'comment' => ''));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/datatable-responsive/datatables.responsive.min.js', 'comment' => ''));
		

		

		$_table = $this -> load -> view('index/table', '', TRUE);

		$attr['data-widget-icon'] = 'fa fa-cubes';
		$_widget_table = widget('objects' . time(), 'Objects', $attr, $_table, false, true, true);

		$js_in_page = $this -> load -> view('index/js', '', TRUE);
		$this -> layout -> add_js_in_page(array('data' => $js_in_page, 'comment' => 'INDEX FUNCTIONS'));

		$data['_table'] = $_widget_table;
		
		$this -> layout -> set_compress(false);
		$this -> layout -> view('index/index', $data);

	}

	public function add() {

		if ($this -> input -> post()) {

			//carico X class database
			$this -> load -> database();
			$this -> load -> model('objects');

			$private = $this -> input -> post('private');

			$_obj_data['user'] = $_SESSION['user']['id'];
			$_obj_data['obj_name'] = $this -> input -> post('name');
			$_obj_data['obj_description'] = $this -> input -> post('description');
			$_obj_data['private'] = $private == 'on' ? 0 : 1;

			//inserisco il nuogo oggetto
			$_obj_id = $this -> objects -> insert_obj($_obj_data);

			//inserisco gli eventuali file dell'oggetto
			$files = explode(',', $this -> input -> post('files'));

			$usb_files = explode(',', $this -> input -> post('usb_files'));

			$usb_files_id = array();

			foreach ($usb_files as $file) {
				if ($file != '') {

					$tmp = str_replace(" ", "_", $file);

					array_push($usb_files_id, $this -> copy_from_usb('/media/usb0/' . $file));
				}

			}

			$this -> objects -> insert_files($_obj_id, $files);
			$this -> objects -> insert_files($_obj_id, $usb_files_id);

			//torno all'homepage del modulo
			//$this->session->set_flashdata('obj_inserted', 'New object '.$_obj_data['obj_name'].' was inserted with success');
			redirect('objectmanager/edit/'.$_obj_id);
		}

		//carico file configurazione
		$this -> config -> load('upload');

		/** LOAD FROM USB DISK FIRST TREE */

		$data['folder_tree'] = array();

		$this -> load -> helper('smart_admin_helper');

		/** LOAD FORM CONTENT */
		$_form = $this -> load -> view('add/form', '', TRUE);
		$_dropzone = $this -> load -> view('add/dropzone', '', TRUE);

		$form_attr['data-widget-icon'] = '';
		$_form_widget = widget('form' . time(), 'Details ', $form_attr, $_form, false, true, true);

		$dropzone_attr['data-widget-icon'] = '';
		$_dropzone_widget = widget('form' . time(), 'Upload ', $dropzone_attr, $_dropzone, false, true, true);

		$data['form'] = $_form_widget;
		$data['dropzone'] = $_dropzone_widget;

		$js_data['accepted_files'] = $this -> config -> item('upload_accepted_files');
		$j_data['_upload_max_filesize'] = ini_get("upload_max_filesize");

		$data['_upload_max_filesize'] = ini_get('upload_max_filesize');

		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/jquery-validate/jquery.validate.min.js', 'comment' => 'VALIDATE FORM'));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/dropzone/dropzone.min.js', 'comment' => 'DROPZONE JAVASCRIPT'));
		
		$this -> layout -> add_css_file(array('src' => '/assets/js/plugin/magnific-popup/magnific-popup.css', 'comment' => 'DROPZONE JAVASCRIPT'));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/magnific-popup/jquery.magnific-popup.min.js', 'comment' => 'DROPZONE JAVASCRIPT'));

		$js_in_page = $this -> load -> view('add/js', $js_data, TRUE);
		$this -> layout -> add_js_in_page(array('data' => $js_in_page, 'comment' => 'INIT DROPZONE'));

		$cc_in_page = $this -> load -> view('add/css', '', TRUE);
		$this -> layout -> add_css_in_page(array('data' => $cc_in_page, 'comment' => ''));

		$this -> layout -> set_compress(false);
		$this -> layout -> view('add/index', $data);

	}

	public function edit($id_object) {

		/** LOAD DATABASE */
		$this -> load -> database();
		$this -> load -> model('objects');
		$this -> load -> model('files');

		/** LOAD HELPERS */
		$this -> load -> helper('smart_admin_helper');
		$this -> load -> helper('ft_date_helper');
		$this -> load -> helper('ft_file_helper');

		if ($this -> input -> post()) {
			$this -> objects -> update($id_object, $this -> input -> post());
		}

		/** LOAD OBJECT */
		$_object = $this -> objects -> get_obj_by_id($id_object);

		/** LOAD FILES ID */
		$_files_id = $this -> objects -> get_files($id_object);
		$_files = array();

		foreach ($_files_id as $id) {

			$_files[] = $this -> files -> get_file_by_id($id);

		}

		$printable_files[] = '.gc';
		$printable_files[] = '.gcode';
		$printable_files[] = '.nc';

		$_widget_data['_id_object'] = $id_object;
		$_widget_data['_files'] = $_files;
		$_widget_data['_printable_files'] = $printable_files;
		$_widget_data['_object'] = $_object;

		/** LOAD TABLE CONTENT */
		$_table_widget_toolbar = $this -> load -> view('edit/table_toolbar', $_widget_data, TRUE);
		$_table = $this -> load -> view('edit/table', $_widget_data, TRUE);

		/** LOAD FORM CONTENT */
		$_form = $this -> load -> view('edit/form', $_widget_data, TRUE);

		/** CREATE WIDGET */
		$attr['data-widget-icon'] = 'fa fa-th-list';
		$_widget_table = widget('objects' . time(), 'Files', $attr, $_table, false, true, false, $_table_widget_toolbar);

		$form_attr['data-widget-icon'] = '';
		$_form_widget = widget('form' . time(), 'Object details ', $form_attr, $_form, false, true, false);

		/** LAYOUT */
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/datatables/jquery.dataTables.min.js', 'comment' => ''));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/datatables/dataTables.colVis.min.js', 'comment' => ''));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/datatables/dataTables.tableTools.min.js', 'comment' => ''));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/datatables/dataTables.bootstrap.min.js', 'comment' => ''));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/datatable-responsive/datatables.responsive.min.js', 'comment' => ''));

		$data['_object'] = $_object;
		$data['_widget'] = $_widget_table;
		$data['_form'] = $_form_widget;

		$js_in_page = $this -> load -> view('edit/js', $data, TRUE);
		$this -> layout -> add_js_in_page(array('data' => $js_in_page, 'comment' => 'EDIT FUNCTIONS'));

		//$this->layout->set_compress(false);
		$this -> layout -> view('edit/index', $data);

	}

	public function delete() {

		//if is only an ajax call request
		if ($this -> input -> is_ajax_request()) {

			$ids = $this -> input -> post("ids");

			//carico X class database
			$this -> load -> database();
			$this -> load -> model('objects');
			$this -> load -> model('files');

			foreach ($ids as $obj_id) {

				$object = $this -> objects -> get_obj_by_id($obj_id);
				$files = $this -> objects -> get_files($obj_id);

				//cancello l'oggetto
				if ($this -> objects -> delete($obj_id)) {

					//cancello i record dei file nella tabella di appoggio
					$this -> objects -> delete_files($obj_id, $files);

					//cancello i file
					foreach ($files as $file) {
						$this -> files -> delete($file);
					}
					//unlink($file->full_path);
				}
			}

			echo json_encode(array('success' => TRUE, 'messagge' => ''));

		} else {
			echo "call not valid";
		}
	}

	public function upload() {

		//carico file configurazione
		$this -> config -> load('upload');

		$upload_dir = $this -> config -> item('upload_dir');
		$accepted_files = str_replace('.', '', $this -> config -> item('upload_accepted_files'));
		$accepted_files = str_replace(',', '|', $accepted_files);

		/*
		 * Verifico l'estensione del file in modo da salvarlo nell cartella esatta, se la cartella non esiste la creo
		 */
		$_tmp_file_name = explode('.', $_FILES['file']['name']);
		$_extension = strtolower(end($_tmp_file_name));

		if ($_FILES['file']['type'] == 'application/vnd.ms-pki.stl') {
			$_FILES['file']['type'] = 'application/octet-stream';
		}

		if (!file_exists($upload_dir . $_extension))// se la cartella non esiste la creo
		{
			mkdir($upload_dir . $_extension, 0777);
		}

		$config['upload_path'] = $upload_dir . $_extension;
		$config['allowed_types'] = '*';
		$config['file_ext_tolower'] = true ;
		$config['remove_spaces']    = true ;
		$config['encrypt_name']     = false;

		//carico la libreria per la gestione dell'upload
		$this -> load -> library('upload', $config);

		if (!$this -> upload -> do_upload('file')) {
			$error = array('error' => $this -> upload -> display_errors());

			print_r($error);
		} else {
			$data = $this -> upload -> data();

			//carico X class database
			$this -> load -> database();
			$this -> load -> model('files');

			/** LOAD FILE HELPER */
			$this -> load -> helper('ft_file_helper');

			/** UTIL PARAMS */
			$_printable_files[] = '.gc';
			$_printable_files[] = '.gcode';
			$_printable_files[] = '.nc';

			$data['file_ext'] = strtolower($data['file_ext']);

			if (in_array($data['file_ext'], $_printable_files)) {
				$data['attributes'] = 'Processing';
			}

			if (in_array($data['file_ext'], $_printable_files)) {

				$data['print_type'] = print_type($data['full_path']);

			}

			$id_file = $this -> files -> insert_file($data);

			/** IF IS A PRINTABLE FILE CHECK THE TYPE OF PRINT - ADDITIVE O SUBTRACTIVE */
			if (in_array($data['file_ext'], $_printable_files)) {

				/** GCODE ANALYZER */
				gcode_analyzer($id_file);

			}

		}

		echo $id_file;

	}

	function select($mode) {

		//carico X class database
		$this -> load -> database();
		$this -> load -> model('files');

		$data['_files'] = $this -> files -> get_all();

		$this -> load -> view('select/' . $mode, $data);
	}

	function object($id) {

		$this -> load -> helper('ft_date_helper');

		$printable = $this -> input -> post('printable');

		$printable_files[] = '.gc';
		$printable_files[] = '.gcode';
		$printable_files[] = '.nc';

		//carico X class database
		$this -> load -> database();
		$this -> load -> model('objects');
		$this -> load -> model('files');

		$_object = $this -> objects -> get_obj_by_id($id);
		$_obj_files = $this -> objects -> get_files($id);

		$_files = array();
		
		;
		
		$_object -> date_insert  = date('d/m/Y', strtotime($_object -> date_insert));
		$_object -> date_updated = date('d/m/Y', strtotime($_object -> date_updated));

		foreach ($_obj_files as $_file) {

			$_temp = $this -> files -> get_file_by_id($_file);

			if ($_temp != '') {

				if ($printable && in_array($_temp -> file_ext, $printable_files)) {
					$_files[$_file] = $_temp;
				}

			}

		}
		echo json_encode(array('object' => $_object, 'files' => array('number' => count($_files), 'data' => $_files)));

	}

	function json() {

		//carico X class database
		$this -> load -> database();
		$this -> load -> model('objects');

		$this -> load -> helper('ft_date_helper');

		$objects = $this -> objects -> get_all();

		$rows = array();

		foreach ($objects as $obj) {

			$_edit_button = '<a href="' . site_url('objectmanager/edit/' . $obj -> id) . '" class="btn btn-default btn-xs"><i class="fa fa-pencil"></i></a>';
			$_delete_button = '<a href="javascript:ask_delete(' . $obj -> id . ', \'' . $obj -> obj_name . '\');" file-id="' . $obj -> id . '" file-name="' . $obj -> obj_name . '" class="btn btn-default btn-xs file-delete txt-color-red"><i class="fa fa-times"></i></a>';

			$icon_file = $obj -> num_files > 1 ? 'fa-files-o' : ' fa-file-o';
			$_files = $obj -> num_files . ' <i class="fa ' . $icon_file . '"></i>';

			$rows[] = array($obj -> obj_name, $obj -> obj_description, date('d/m/Y', strtotime($obj -> date_insert)), $_files, $_edit_button . ' ' . $_delete_button);
			
			
		}

		echo json_encode(array('aaData' => $rows));

	}

	public function file($action, $object_id = 0, $file_id = 0) {
		
		$this -> load -> model('objects');
		
		$data['_object_id'] = $object_id;
		$data['_file_id'] = $file_id;
		
		$data['object'] = $this->objects->get_obj_by_id($object_id);
		
		$this -> load -> helper('object_helper');

		if ($action == 'add') {

			if ($this -> input -> post()) {

				//carico X class database
				$this -> load -> database();
				

				$files = explode(',', $this -> input -> post('files'));
				$usb_files = explode(',', $this -> input -> post('usb_files'));

				$usb_files_id = array();

				foreach ($usb_files as $file) {
					if ($file != '') {
						$tmp = str_replace(" ", "_", $file);
						array_push($usb_files_id, $this -> copy_from_usb('/media/usb0/' . $file));
					}

				}

				$this -> objects -> insert_files($this -> input -> post('object'), $files);
				$this -> objects -> insert_files($this -> input -> post('object'), $usb_files_id);

				redirect('objectmanager/edit/' . $this -> input -> post('object'), 'location');

			}

			/** LOAD UPLOAD CONFIG */
			$this -> config -> load('upload');

			$js_data['accepted_files'] = $this -> config -> item('upload_accepted_files');
			$js_data['_upload_max_filesize'] = ini_get("upload_max_filesize");
			$js_data['_action'] = $action;
			$js_data['_object_id'] = $object_id;
			$js_data['_time'] = $data['_time'] = time();

			$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/dropzone/dropzone.min.js', 'comment' => 'DROPZONE JAVASCRIPT'));
			
			$this -> layout -> add_css_file(array('src' => '/assets/js/plugin/magnific-popup/magnific-popup.css', 'comment' => 'DROPZONE JAVASCRIPT'));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/magnific-popup/jquery.magnific-popup.min.js', 'comment' => 'DROPZONE JAVASCRIPT'));
			
			$js_in_page = $this -> load -> view('file/add/js', $js_data, TRUE);
			$this -> layout -> add_js_in_page(array('data' => $js_in_page, 'comment' => 'INIT DROPZONE'));

			/** LOAD FROM USB DISK FIRST TREE */
			$data['folder_tree'] = array();

			/*
			 if(file_exists('/dev/sda1')){

			 $_destination = '/var/www/fabui/application/modules/objectmanager/temp/media.json';
			 $_command = 'sudo python /var/www/fabui/python/usb_browser.py  --dest=' . $_destination;
			 shell_exec($_command);
			 //sleep ( 1);

			 $data['folder_tree'] = json_decode(file_get_contents($_destination), TRUE);

			 }
			 */

			$data['_action'] = $action;

		}

		if ($action == "view") {

			/** LOAD HELPER */
			$this -> load -> helper('ft_file_helper');

			//carico X class database
			$this -> load -> database();
			$this -> load -> model('files');
			$file = $this -> files -> get_file_by_id($file_id);

			$data['_success'] = false;

			$attributes = json_decode($file -> attributes, TRUE);

			$data['_file'] = $file;
			$data['is_stl'] = strtolower($file -> file_ext) == '.stl';

			/** IF NOT A STL FILE, GET GCODE MODEL INFO */
			if (!$data['is_stl']) {

				$data['dimesions'] = 'processing';
				$data['filament'] = 'processing';
				$data['number_of_layers'] = 'processing';
				$data['estimated_time'] = 'processing';

				if (is_array($attributes)) {
					$dimensions = $attributes['dimensions'];

					$x = number_format($dimensions['x'], 2, '.', '');
					$y = number_format($dimensions['y'], 2, '.', '');
					$z = number_format($dimensions['z'], 2, '.', '');

					$data['dimesions'] = $x . ' x ' . $y . ' x ' . $z . ' mm';
					$data['filament'] = number_format($attributes['filament'], 2, '.', '') . ' mm';
					$data['number_of_layers'] = $attributes['number_of_layers'];
					$data['estimated_time'] = $attributes['estimated_time'];

				} else {

					if (strtolower($file -> file_ext) == '.gc' || strtolower($file -> file_ext) == '.gcode' && $file -> attributes != 'Processing') {
						gcode_analyzer($file -> id);
					}

				}
			}

			$data['printables_files'] = array('.gc', '.gcode', '.nc');
			$data['preview_files'] = array('.gc', '.gcode', '.stl');

			$js_in_page = $this -> load -> view('file/view/js', $data, TRUE);
			$css_in_page = $this -> load -> view('file/view/css', '', TRUE);

			/** LAYOUT SETUP */
			$this -> layout -> add_js_in_page(array('data' => $js_in_page, 'comment' => ''));
			$this -> layout -> add_css_in_page(array('data' => $css_in_page, 'comment' => ''));
			$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/ace/src-min/ace.js', 'comment' => 'ACE EDITOR JAVASCRIPT'));

			$this -> layout -> set_compress(false);

		}

		if ($action == 'preview') {

			//carico X class database
			$this -> load -> database();
			$this -> load -> model('files');
			$file = $this -> files -> get_file_by_id($file_id);
			$data['file'] = $file;

			if (strtolower($file -> file_ext) == '.stl') {

				$this -> load -> helper('smart_admin_helper');

				$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/thingiview/Three.js', 'comment' => ''));
				$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/thingiview/plane.js', 'comment' => ''));
				$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/thingiview/thingiview.js', 'comment' => ''));

				$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/colorpicker/bootstrap-colorpicker.min.js', 'comment' => ''));

				$attr['data-widget-fullscreenbutton'] = 'false';
				$attr['data-widget-icon'] = 'fa fa-cube';
				$widget_id = 'render_area' . time();
				$widget_content = $this -> load -> view('file/preview/stl/widget', '', TRUE);
				$_widget_preview = widget($widget_id, 'Stl Viewer', $attr, $widget_content, false, false, true);

				$data['widget'] = $_widget_preview;
				$data['widget_id'] = $widget_id;

				$js_in_page = $this -> load -> view('file/preview/stl/js', $data, TRUE);
				$this -> layout -> add_js_in_page(array('data' => $js_in_page, 'comment' => ''));

				$action .= '/stl';

			} else {

				/** LOAD HELPER */
				$this -> load -> helper('ft_file_helper');
				$this -> load -> helper('smart_admin_helper');

				$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/gcodeviewer/modernizr.custom.93389.js', 'comment' => ''));
				$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/gcodeviewer/sugar-1.2.4.min.js', 'comment' => ''));
				$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/gcodeviewer/Three.js', 'comment' => ''));
				$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/gcodeviewer/three.TrackballControls.js', 'comment' => ''));
				$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/gcodeviewer/gcode-parser.js', 'comment' => ''));
				$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/gcodeviewer/gcode-model.js', 'comment' => ''));
				$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/gcodeviewer/renderer.js', 'comment' => ''));

				$attributes = json_decode($file -> attributes, TRUE);

				$data_widget['dimesions'] = 'processing';
				$data_widget['filament'] = 'processing';
				$data_widget['number_of_layers'] = 'processing';
				$data_widget['estimated_time'] = 'processing';

				if (is_array($attributes)) {

					$dimensions = $attributes['dimensions'];
					$x = number_format($dimensions['x'], 2, '.', '');
					$y = number_format($dimensions['y'], 2, '.', '');
					$z = number_format($dimensions['z'], 2, '.', '');

					$data_widget['dimesions'] = $x . ' x ' . $y . ' x ' . $z . ' mm';
					$data_widget['filament'] = number_format($attributes['filament'], 2, '.', '') . ' mm';
					$data_widget['number_of_layers'] = $attributes['number_of_layers'];
					$data_widget['estimated_time'] = $attributes['estimated_time'];

				} else {

					if (strtolower($file -> file_ext) == '.gc' || strtolower($file -> file_ext) == '.gcode' && $file -> attributes != 'Processing') {
						gcode_analyzer($file -> id);
					}

				}

				$attr['data-widget-fullscreenbutton'] = 'false';
				$attr['data-widget-icon'] = 'fa fa-cube';
				$widget_id = 'render_area' . time();
				$widget_content = $this -> load -> view('file/preview/gcode/widget', $data_widget, TRUE);
				$_widget_preview = widget($widget_id, 'GCode Viewer', $attr, $widget_content, false, false, true);

				$data['widget'] = $_widget_preview;
				$data['widget_id'] = $widget_id;

				$css_in_page = $this -> load -> view('file/preview/gcode/css', '', TRUE);
				$js_in_page = $this -> load -> view('file/preview/gcode/js', $data, TRUE);

				$this -> layout -> add_css_in_page(array('data' => $css_in_page, 'comment' => ''));
				$this -> layout -> add_js_in_page(array('data' => $js_in_page, 'comment' => ''));

				$this -> layout -> set_compress(false);

				$action .= '/gcode';

			}

		}

		if ($action == 'stats') {
			$this->stats($object_id, $file_id);
			return;
		}

		$this -> layout -> view('file/' . $action . '/index', $data);

	}

	public function download($type, $list) {

		$this -> load -> database();
		$this -> load -> model('files');
		$this -> load -> model('objects');

		$download_folder = 'download';
		//shell_exec('sudo rm -r download');
		//mkdir(FCPATH.'/'.$download_folder, 0777);
		shell_exec('sudo mkdir ' . FCPATH . '/' . $download_folder);
		shell_exec('sudo chmod 0777 ' . FCPATH . '/download');

		/** LOAD HELPER */
		$this -> load -> helper('download');

		if ($type == 'file') {
			$files = explode('-', $list);

			if (count($files) > 0) {

				if (count($files) == 1) {

					$_file = $this -> files -> get_file_by_id($files[0]);
					$data = file_get_contents($_file -> full_path);
					force_download($_file -> file_name, $data);
				} else {

					foreach ($files as $file_id) {

						$file = $this -> files -> get_file_by_id($file_id);
						shell_exec('cp ' . $file -> full_path . ' ' . $download_folder);
						shell_exec('sudo cp "' . $file -> full_path . '" ' . FCPATH . '/' . $download_folder);
					}

					shell_exec('sudo chmod -R 0777 ' . FCPATH . $download_folder);

					//zip file
					$this -> load -> library('zip');
					$this -> zip -> read_dir($download_folder . '/');
					$this -> zip -> download('fabtotum_files.zip');

				}
			}
		} else if ($type == 'object') {

			$objects = explode('-', $list);
			if (count($objects) > 0) {

				//crate download temp folder
				shell_exec('sudo chmod -R 0777 ' . FCPATH . '/' . $download_folder);

				foreach ($objects as $obj_id) {

					$obj = $this -> objects -> get_obj_by_id($obj_id);

					$obj_folder = $download_folder . '/' . str_replace('&', 'and',str_replace(' ', '_', $obj -> obj_name));

					$obj_folder = str_replace('(', '_', $obj_folder);
					$obj_folder = str_replace(')', '_', $obj_folder);

					//create objec folder

					mkdir($obj_folder, 0777);
					shell_exec('sudo chmod -R 0777 ' . FCPATH . $obj_folder);

					$files = $this -> objects -> get_files($obj_id);

					foreach ($files as $file_id) {
						$file = $this -> files -> get_file_by_id($file_id);
						shell_exec('sudo cp "' . $file -> full_path . '" ' . FCPATH . $obj_folder . '/');
					}
				}

				//zip file
				shell_exec('sudo chmod -R 0777 ' . FCPATH . $download_folder);

				$this -> load -> library('zip');
				$this -> zip -> read_dir($download_folder . '/');
				$this -> zip -> download('fabtotum_objects.zip');

			}
		}

	}

	public function delete_file() {

		if ($this -> input -> is_ajax_request()) {

			$files = $this -> input -> post("ids");

			//carico X class database
			$this -> load -> database();
			$this -> load -> model('files');
			$this -> load -> model('objects');

			foreach ($files as $id_file) {

				$_file = $this -> files -> get_file_by_id($id_file);
				$id_object = $this -> objects -> get_by_file($id_file);
				$this -> objects -> delete_files($id_object, array($id_file));
				$this -> files -> delete($id_file);
			}

			header('Content-Type: application/json');
			echo json_encode(array('success' => TRUE, 'messagge' => ''));

		} else {
			echo "call not valid";
		}

	}

	public function manage($obj, $file_id) {

		$this -> load -> database();
		$this -> load -> model('files');

		$_file = $this -> files -> get_file_by_id($file_id);

		$data['obj_id'] = $obj;
		$data['file'] = $_file;

		$_extension = str_replace('.', '', strtolower($_file -> file_ext));

		switch($_extension) {

			case 'asc' :
				$data['first_box_title'] = 'Reconstruction';
				$data['first_box_desc'] = 'This experimental feature takes the selected cloud data and process it into a solid STL file that can be printed.';
				$data['first_box_img'] = '<img style="max-width: 50%; display: inline;" class="img-responsive" src="' . module_url('objectmanager') . '/assets/img/reconstruction.png" />';
				$data['first_box_action'] = 'asc';

				break;

			case 'stl' :
				$data['first_box_title'] = 'Slicing';
				$data['first_box_desc'] = 'This experimental feature takes the selected STL file and turns it into a printable model (additive manufacturing only).';
				$data['first_box_img'] = '<img style="max-width: 50%; display: inline;" class="img-responsive" src="' . module_url('objectmanager') . '/assets/img/slicing.png" />';
				$data['first_box_action'] = 'stl';
				break;

			case 'gcode' :
			case 'gc' :
			case 'nc' :
				$data['first_box_title'] = 'Print';
				$data['first_box_desc'] = 'Print the file';
				$data['first_box_img'] = '<i class="img-responsive icon-fab-print" style="font-size: 189px;"></i>';
				$data['first_box_action'] = 'print';
				break;
		}
		$_is_asc = strtolower($_file -> file_ext) == '.asc';

		$this -> layout -> view('manage/index', $data);

	}

	/**
	 *
	 * PREAPARE FILE,
	 *
	 */
	function prepare($type, $object, $file) {

		switch($type) {
			case 'stl' :
				$this -> g_code($object, $file);
				break;
			case 'asc' :
				$this -> stl($object, $file);
				break;
			case 'merge' :
				$this -> merge($object, $file);
				break;
			case 'print' :
				redirect('make/print?obj=' . $object . '&file=' . $file);
				break;
		}

	}

	/**
	 *  CREATE GCODE FROM STL FILE
	 *
	 */
	function g_code($object, $file) {

		//carico X class database
		$this -> load -> database();
		$this -> load -> model('files');
		$this -> load -> model('configuration');
		$this -> load -> model('tasks');

		//load helpers
		$this -> load -> helper("ft_file_helper");

		/** CHEK IF THERE IS AN OPEN TASK */
		$_task = $this -> tasks -> get_running('objectmanager', 'slice');

		$_file = $this -> files -> get_file_by_id($file);

		$file_size = filesize($_file -> full_path);

		$_presets = json_decode($this -> configuration -> get_config_value('slicer_presets'), true);

		$data['_task'] = $_task;
		$data['_object'] = $object;
		$data['_file'] = $_file;
		$data['_presets'] = $_presets;
		$data['file_size'] = $file_size;
		$data['alert_size'] = $file_size >= 10485760 ? true : false;

		//if file size bigger than 10MB
		//if($file_size >= 10485760){
		//
		//	$data['alert_size'] = true;
		//}

		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/dropzone/dropzone.min.js', 'comment' => 'DROPZONE JAVASCRIPT'));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/ace/src-min/ace.js', 'comment' => 'ACE EDITOR JAVASCRIPT'));

		$this -> layout -> set_compress(false);

		$js_in_page = $this -> load -> view('prepare/gcode/js', $data, TRUE);
		$ccs_in_page = $this -> load -> view('prepare/gcode/css', $data, TRUE);
		$this -> layout -> add_js_in_page(array('data' => $js_in_page, 'comment' => ''));
		$this -> layout -> add_css_in_page(array('data' => $ccs_in_page, 'comment' => ''));
		$this -> layout -> view('prepare/gcode/index', $data);

	}

	/**
	 *  CREATE STL FROM ASC FILE
	 *
	 */
	function stl($object, $file) {

		//carico X class database
		$this -> load -> database();
		$this -> load -> model('files');
		$this -> load -> model('configuration');
		$this -> load -> model('tasks');

		/** CHEK IF THERE IS AN OPEN TASK */
		$_task = $this -> tasks -> get_running('objectmanager', 'meshlab');

		$_file = $this -> files -> get_file_by_id($file);

		$data['_object'] = $object;
		$data['_file'] = $_file;
		$data['_task'] = $_task;

		$js_in_page = $this -> load -> view('prepare/stl/js', $data, TRUE);
		$this -> layout -> add_js_in_page(array('data' => $js_in_page, 'comment' => ''));
		$this -> layout -> view('prepare/stl/index', $data);

	}

	public function merge($obj_id, $file_id) {

		/** LOAD DATABASE */
		$this -> load -> database();
		$this -> load -> model('objects');
		$this -> load -> model('files');

		/** LOAD HELPERS */
		$this -> load -> helper('smart_admin_helper');
		$this -> load -> helper('ft_date_helper');
		$this -> load -> helper('ft_file_helper');

		/** LOAD OBJECT */
		$_object = $this -> objects -> get_obj_by_id($obj_id);

		/** LOAD FILES ID */
		$_files_id = $this -> objects -> get_files($obj_id);
		$_files = array();

		foreach ($_files_id as $id) {

			$_file_temp = $this -> files -> get_file_by_id($id);

			if (strtolower($_file_temp -> file_ext) == '.asc') {

				$_files[] = $_file_temp;

			}

		}

		$data['obj_id'] = $obj_id;
		$data['files'] = $_files;
		$data['file_id'] = $file_id;

		$_table = $this -> load -> view('prepare/merge/table', $data, TRUE);
		//$_widget_table = widget('files'.time(), 'Select files to merge',  '', $_table, false, true);

		$data['table'] = $_table;

		$js_in_page = $this -> load -> view('prepare/merge/js', $data, TRUE);
		$this -> layout -> add_js_in_page(array('data' => $js_in_page, 'comment' => ''));
		$this -> layout -> view('prepare/merge/index', $data);
	}

	/** */
	function copy_from_usb($file) {

		/** LOAD FILE HELPER */
		$this -> load -> helper('file');
		$this -> load -> helper('ft_file_helper');

		$file_name = explode("/", $file);
		$file_name = end($file_name);

		/** MOVE TO TEMP FOLDER */
		$_command = 'sudo cp "' . $file . '"  "/var/www/temp/' . $file_name . '" ';

		//echo $_command.PHP_EOL;

		shell_exec($_command);

		$file = '/var/www/temp/' . $file_name;

		$file_information = get_file_info($file);

		$file_extension = get_file_extension($file);

		$folder_destination = '/var/www/upload/' . str_replace('.', '', $file_extension) . '/';

		$file_name = set_filename($folder_destination, $file_name);

		/** MOVE TO FINALLY FOLDER */
		$_command = 'sudo cp "' . $file . '" "' . $folder_destination . $file_name . '" ';
		shell_exec($_command);

		//echo $_command.PHP_EOL;

		/** ADD PERMISSIONS */
		$_command = 'sudo chmod 746 "' . $folder_destination . $file_name . '" ';
		shell_exec($_command);

		/** INSERT RECORD TO DB */
		//carico X class database
		$this -> load -> database();
		$this -> load -> model('files');

		$data['file_name'] = $file_name;
		$data['file_path'] = $folder_destination;
		$data['full_path'] = $folder_destination . $file_name;
		$data['raw_name'] = str_replace($file_extension, '', $file_name);
		$data['orig_name'] = $file_name;
		$data['file_ext'] = $file_extension;
		$data['file_size'] = $file_information['size'];
		$data['print_type'] = print_type($folder_destination . $file_name);

		/** REMOVE TEMP FILE */
		unlink($file);

		/** RETURN  */
		return $this -> files -> insert_file($data);

	}

	public function slicer_config_upload() {

		//carico file configurazione
		$this -> config -> load('upload');

		$upload_dir = $this -> config -> item('slicer_config_dir');
		$accepted_files = "*";
		$accepted_files = ".ini";

		///var/www/fabui/slic3r/configs

		$config['upload_path'] = $upload_dir;
		$config['allowed_types'] = '*';
		$config['file_ext_tolower'] = true ;
		$config['remove_spaces']    = true ;
		$config['encrypt_name']     = false;

		//carico la libreria per la gestione dell'upload
		$this -> load -> library('upload', $config);

		if (!$this -> upload -> do_upload('file')) {
			$error = array('error' => $this -> upload -> display_errors());
			$status = 'ko';
		} else {
			$data = $this -> upload -> data();
			$status = 'ok';

			$_response_items['file_path'] = $data['full_path'];

			chmod($data['full_path'], 0775);

		}

		$_response_items['status'] = $status;
		echo json_encode($_response_items);

	}

	public function download_slicer_config() {

		if ($this -> input -> post()) {

			$config = $this -> input -> post('dsc', FALSE);
			$name = trim($this -> input -> post('nsc'));

			$name = str_replace('-', '_', $name);
			$name = str_replace(' ', '_', $name);
			$name = str_replace('__', '_', $name);

			$name .= '.ini';

			$this -> load -> helper('download');
			force_download($name, $config);
		}

	}

	public function slicer_manual() {

		$this -> load -> helper('file');

		$manual_file = "./slic3r/manual.txt";

		if (file_exists($manual_file)) {
			$manual = read_file($manual_file);
		} else {
			$manual = shell_exec('sudo /var/www/fabui/slic3r/slic3r --help');
			write_file($manual_file, $manual);
		}

		$help = strstr($manual, '--help');

		$contents = explode('--', $help);

		$parameters = array();

		$no_show[] = 'help';
		$no_show[] = 'version';
		$no_show[] = 'save';
		$no_show[] = 'load';
		$no_show[] = 'repair';
		$no_show[] = 'cut';
		$no_show[] = 'info';
		$no_show[] = 'threads';
		$no_show[] = 'no_plater';
		$no_show[] = 'gui_mode';
		$no_show[] = 'autosave';
		$no_show[] = 'output_filename_format';
		$no_show[] = 'post_process';
		$no_show[] = 'export_svg';
		$no_show[] = 'merge';

		$i = 0;

		foreach ($contents as $line) {

			$string = explode(" ", $line, 2);
			$name = trim(str_replace('-', '_', str_replace(" ", "", $string[0])));

			if (!in_array($name, $no_show)) {

				if (strlen($name) > 0) {

					$desc = isset($string[1]) ? trim(preg_replace('/\s+/', ' ', $string[1])) : '';
					array_push($parameters, array('name' => $name, 'desc' => $desc));

				}
			}
		}

		$data['parameters'] = $parameters;

		$this -> load -> view('prepare/gcode/manual', $data);

	}

	public function stats($object, $file) {
		
		//$this -> load -> model('tasks');
		$this -> load -> model('files');
		$this -> load -> model('objects');
		$this -> load -> helper('smart_admin_helper');
		$this -> load -> helper('object_helper');
		
		
		$params  = $this->input->get();
		
		$data['start_date'] = !isset($params['start_date']) ? date('d/m/Y',strtotime('today - 30 days')) : $params['start_date'];
		$data['end_date']   = !isset($params['end_date'])  ? date('d/m/Y',strtotime('today')) : $params['end_date'];
		
		//get data from db
		$data['file']   = $this->files->get_file_by_id($file);
		$data['object'] = $this->objects->get_obj_by_id($object);

		$status = array();
		
		$stats = $this->get_statistics($file, $data['start_date'], $data['end_date']);
		
		$data['statistics'] = $stats['statistics'];
		$data['durations'] = $stats['durations'];
		
		$data['totals'] = array();
		
		foreach($data['statistics'] as  $val){
			
			foreach($val as $key => $c){			
				if($key != 'period'){

					if(!isset($data['totals'][$key])) $data['totals'][$key] = 0;
					$data['totals'][$key] += $c;
				}
			}
		}

		$data['options'] = array(
			'performed' => array('label' => 'Completed', 'color' => '#7e9d3a'),
			'stopped'   => array('label' => 'Aborted',   'color' => '#FF9F01'),
			'deleted'   => array('label' => 'Stopped',   'color' => '#a90329'),
			'error'     => array('label' => 'Error',     'color' => '#a90329')
		);
		
		
		$data['labels'] = array();
		$data['line_colors'] = array();
		$data['donut_data'] = array();
		$data['status_keys'] = array();
		$data['total_durations'] = array();
		
		
		foreach($data['options'] as $status => $attributes){
			
			array_push($data['labels'], $data['options'][$status]['label']);
			array_push($data['line_colors'], $data['options'][$status]['color']);
			array_push($data['status_keys'], $status);
			
			//if(count($data['statistics'])>0){

				$temp_tot = isset($data['totals'][$status]) ? $data['totals'][$status] : 0;
				@$value = number_format(($temp_tot / array_sum($data['totals']))*100, 1, '.', ' ');
				
				$temp = array('value' => $value, 'label'=>$data['options'][$status]['label']);
				array_push($data['donut_data'],  $temp);
			
			//}
			
			if(count($data['durations']) > 0 && isset($data['durations'][$status])){
				$data['total_durations'][] = sumTimes($data['durations'][$status]);
			}
			
		}
		
		
		$data['total_durations'] = sumTimes($data['total_durations']);
		//widget
		$data['widget'] = $this -> load -> view('file/stats/widget', $data, TRUE);
		$attr['data-widget-icon'] = 'fa fa-area-chart';
		$data['widget'] = widget('stats' . time(), 'Stats - '.$data['file']->raw_name, $attr, $data['widget'], false, false, false);
		
		// == layout
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/morris/raphael.min.js', 'comment' => 'charts'));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/morris/morris.min.js', 'comment' => 'charts'));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/bootstrap-datepicker/moment.min.js',  'comment' => ''));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/bootstrap-datepicker/daterangepicker.min.js',  'comment' => ''));
		$this -> layout -> add_css_file(array('src' => '/assets/js/plugin/bootstrap-datepicker/daterangepicker.css',  'comment' => ''));
		
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/datatables/jquery.dataTables.min.js', 'comment' => ''));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/datatables/dataTables.colVis.min.js', 'comment' => ''));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/datatables/dataTables.tableTools.min.js', 'comment' => ''));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/datatables/dataTables.bootstrap.min.js', 'comment' => ''));
		$this -> layout -> add_js_file(array('src' => '/assets/js/plugin/datatable-responsive/datatables.responsive.min.js', 'comment' => ''));
		
		$this -> layout -> add_js_in_page(array('data' => $this -> load -> view('file/stats/js', $data, TRUE), 'comment' => 'EDIT FUNCTIONS'));
		$this -> layout -> add_css_in_page(array('data' => $this -> load -> view('file/stats/css', $data, TRUE), 'comment' => 'CSS'));
		$this -> layout -> set_compress(false);
		
		
		
		$this -> layout -> view('file/stats/index', $data);
	}


	function get_statistics($file, $start_date, $end_date){
		
		$this -> load -> model('tasks');
		$stats = $this->tasks->get_file_stats($file, $start_date, $end_date);
		
		
		
		//print_r($stats); 
		
		$temp_stats = array();
		$durations_stats = array();
		//construct stats
		foreach($stats as $stat){
			
			if(!isset($temp_stats[$stat['date']])) $temp_stats[$stat['date']] = array();
			if(!isset($temp_stats[$stat['date']][$stat['status']])) $temp_stats[$stat['date']][$stat['status']] = 0;
			
			$temp_stats[$stat['date']][$stat['status']] += $stat['total']; 
			
			if(!isset($durations_stats[$stat['status']])) $durations_stats[$stat['status']] = array();
			$durations_stats[$stat['status']][] = $stat['total_time'];
		}

		
		
		$statistics = array();
		
		foreach($temp_stats as $day => $content){
			
			$temp = array('period'=>$day);
			
			foreach($content as $status => $total){
				
				$temp[$status] = $total;
				
			}	
			array_push($statistics, $temp);
			
		}
		
		return  array ('statistics' =>$statistics, 'durations' => $durations_stats);		
		
	}
	
	
	function get_json_stats_data($file, $start_date, $end_date){
		
		$start_date = date('d/m/Y', ($start_date/1000));
		$end_date   = date('d/m/Y', ($end_date/1000));
		
		$options = array(
			'performed' => array('label' => 'Completed', 'color' => '#7e9d3a'),
			'stopped'   => array('label' => 'Aborted',   'color' => '#FF9F01'),
			'deleted'   => array('label' => 'Stopped',   'color' => '#a90329'),
			'error'     => array('label' => 'Error',     'color' => '#a90329')
		);
		
		$stats =  $this->get_statistics($file, $start_date, $end_date);
		$statistics = $stats['statistics'];
		$durations = $stats['durations'];
		
		
		if(count($statistics)<=0){
			echo json_encode(array('line'=>array(), 'donut'=>array(), 'tasks'=>array(), 'total_tasks'=>0, 'total_duration' => 0, 'durations' => array(), 'bars'=>''), JSON_NUMERIC_CHECK );
			return;
		}
		
		$totals = array();
		$status_keys = array();
		foreach($statistics as  $val){
			
			foreach($val as $key => $c){			
				if($key != 'period'){
					
					if(!in_array($key, $status_keys)) array_push($status_keys, $key);
							
					if(!isset($totals[$key])) $totals[$key] = 0;
					$totals[$key] += $c;
				}
			}
		}
		
		$donut_data = array();
		$total_duration_temp = array();
		$total_durations = array();
	
		foreach($options as $status => $attributes){
			
			$temp_tot = isset($totals[$status]) ? $totals[$status] : 0;
			@$value = number_format(($temp_tot / array_sum($totals))*100, 1, '.', ' ');
			$temp = array('value' => $value, 'label'=>$options[$status]['label']);
			array_push($donut_data,  $temp);
			
			//echo $status;
			if(count($durations) > 0 && isset($durations[$status])){
				$total_duration_temp[] = sumTimes($durations[$status]);
				$total_durations[$status] = sumTimes($durations[$status]);
			}
			
		}
		
		$total_duration = sumTimes($total_duration_temp);
		
		
		$html_bars = '<div>';
		foreach($options as $status => $attributes){
			if(isset($totals[$status])){
				$html_bars .= '<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12"><span class="text">'.$attributes['label'].'<span class="pull-right">'.$totals[$status].'/ '.array_sum($totals).'</span></span></div>';
				$html_bars .= '<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12"><span class="text"><span class="pull-right">'.$total_durations[$status].'/ '.$total_duration.' </span></span></div>';
				$html_bars .= '<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12"><div class="progress"><div class="progress-bar" style="width:'.(($totals[$status]/array_sum($totals))*100).'%; background-color:'.$attributes['color'].' !important;"></div></div></div>';
			}
		}
		$html_bars .= '</div>';
		
		
		echo json_encode(array('line'=>$statistics, 'donut'=>$donut_data, 'tasks' => $totals, 'total_tasks' => array_sum($totals), 'total_duration' => $total_duration, 'durations'=>$total_durations, 'bars'=>$html_bars), JSON_NUMERIC_CHECK );
	}


	function get_file_tasks($id_file, $filters){
		
		$this -> load -> model('tasks');
		return $this->tasks->get_file_tasks($id_file, $filters);
	}

	function get_file_tasks_for_table($file){
		
		$params = $this->input->get();
		
		$this -> load -> helper('ft_date_helper');
		
		$tasks = $this->get_file_tasks($file, $params);
		
		
		
		$options = array(
			'performed' => array('label' => 'Completed', 'color' => '#7e9d3a'),
			'stopped'   => array('label' => 'Aborted',   'color' => '#FF9F01'),
			'deleted'   => array('label' => 'Stopped',   'color' => '#a90329'),
			'error'     => array('label' => 'Error',     'color' => '#a90329')
		);
		
		$aaData = array();

		foreach ($tasks as $task) {
			
			$td_0 = date('d/m/Y H:i:s', strtotime($task['finish_date']));
			$td_1 = $options[$task['status']]['label'];
			$td_2 = $task['duration'];
			$td_3 = $task['status'];
			
			$aaData[] = array($td_0, $td_1, $td_2, $td_3);
		}
		
		echo json_encode(array('aaData' => $aaData));
		
	}

}
?>