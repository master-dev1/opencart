<?php
class ControllerExtensionTranslation extends Controller {
	private $error = array();
	
	public function index() {
		$this->load->language('extension/translation');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->getList();
	}
	
	public function getList() {
		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/translation', 'token=' . $this->session->data['token'], true)
		);

		$data['translations'] = array();
		
		$filter_data = array(
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);
		
		// Make a CURL request 
		$curl = curl_init('https://s3.amazonaws.com/opencart-language/2.0.0.x.json');

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

		$response = curl_exec($curl);

		if (!$response) {
			$data['warning'] = sprintf($this->language->get('error_api'), curl_error($curl), curl_errno($curl));
		} else {
			$translations = json_decode($response, true);
		}
		
		curl_close($curl);		
		
		$translation_total = count($translations);

		if ($translations) {
			$translations = array_splice($translations, ($page - 1) * $this->config->get('config_limit_admin'), $this->config->get('config_limit_admin'));
			
			foreach ($translations as $translation){
				if (is_dir(DIR_LANGUAGE . strtolower($translation['code']))) {
					$installed = true;
				} else {
					$installed = false;
				}
				
				$data['translations'][] = array(
					'name'      => $translation['name'],
					'code'      => $translation['code'],
					'image'     => 'https://d1ztvzf22lmr1j.cloudfront.net/images/flags/' . $translation['code'] . '.png',
					'progress'  => $translation['translated_progress'],
					'install'   => $this->url->link('extension/translation/install', 'token=' . $this->session->data['token'] . '&code=' . $translation['code'], true),
					'uninstall' => $this->url->link('extension/translation/uninstall', 'token=' . $this->session->data['token'] . '&code=' . $translation['code'], true),
					'installed' => $installed
				);
			}
		}
		
		$data['heading_title'] = $this->language->get('heading_title');

        $data['text_list'] = $this->language->get('text_list');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['text_confirm'] = $this->language->get('text_confirm');
		$data['text_progress'] = $this->language->get('text_progress');
		$data['text_available'] = $this->language->get('text_available');
		$data['text_crowdin'] = $this->language->get('text_crowdin');
		$data['text_loading'] = $this->language->get('text_loading');
		
		$data['column_flag'] = $this->language->get('column_flag');
		$data['column_name'] = $this->language->get('column_name');
		$data['column_progress'] = $this->language->get('column_progress');
        $data['column_action'] = $this->language->get('column_action');

		$data['entry_progress'] = $this->language->get('entry_progress');
		
		$data['button_refresh'] = $this->language->get('button_refresh');
		$data['button_clear'] = $this->language->get('button_clear');
		$data['button_install'] = $this->language->get('button_install');
		$data['button_uninstall'] = $this->language->get('button_uninstall');
		
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
		
		$data['token'] = $this->session->data['token'];

		$directories = glob(ini_get('upload_tmp_dir') . '/lng-*');

		if ($directories) {
			$data['error_warning'] = $this->language->get('error_temporary');
		} else {
			$data['error_warning'] = '';
		}

		$pagination = new Pagination();
		$pagination->total = $translation_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('extension/translation', 'token=' . $this->session->data['token'] . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($translation_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($translation_total - $this->config->get('config_limit_admin'))) ? $translation_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $translation_total, ceil($translation_total / $this->config->get('config_limit_admin')));

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/translation', $data));
	}

	public function install() {
		$this->load->language('extension/translation');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/installer')) {
			$json['error'] = $this->language->get('error_permission');
		}
		
		if (isset($this->request->get['code'])) {
			$code = $this->request->get['code'];
		} else {
			$code = '';
		}
						
		if (!$json) {
			$curl = curl_init('https://crowdin.com/download/project/opencart/' . $code . '.zip');
	
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, 'json=true');
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLOPT_TIMEOUT, 30);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	
			$response = curl_exec($curl);
	
			if (!$response) {
				$json['error'] = sprintf($this->language->get('error_api'), curl_error($curl), curl_errno($curl));
			} else {
				$file = ini_get('upload_tmp_dir') . '/lng-' . $code . '.zip';
		
				$handle = fopen($file, 'w');
		
				flock($handle, LOCK_EX);
		
				fwrite($handle, $response);
		
				fflush($handle);
		
				flock($handle, LOCK_UN);
		
				fclose($handle);
				
				$json['success'] = $this->language->get('text_download');
				
				$json['next'] = str_replace('&amp;', '&', $this->url->link('extension/translation/unzip', 'token=' . $this->session->data['token'] . '&code=' . $code, true));		
			}
			
			curl_close($curl);	
		}
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));				
	}
	
	public function unzip() {
		$this->load->language('extension/translation');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/translation')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (isset($this->request->get['code'])) {
			$code = $this->request->get['code'];
		} else {
			$code = '';
		}

		$file = ini_get('upload_tmp_dir') . '/lng-' . $code . '.zip';
		
		if (!is_file($file) || substr(str_replace('\\', '/', realpath($file)), 0, strlen(ini_get('upload_tmp_dir'))) != str_replace('\\', '/', ini_get('upload_tmp_dir'))) {
			$json['error'] = $this->language->get('error_file');
		}
			
		if (!$json) {	
			// Unzip the files
			$zip = new ZipArchive();

			if ($zip->open($file)) {
				$zip->extractTo(ini_get('upload_tmp_dir') . '/lng-' . $code . '/');
				$zip->close();
			} else {
				$json['error'] = $this->language->get('error_unzip');
			}

			// Remove Zip
			unlink($file);
			
			$json['success'] = $this->language->get('text_download');
				
			$json['next'] = str_replace('&amp;', '&', $this->url->link('extension/translation/move', 'token=' . $this->session->data['token'] . '&code=' . $code, true));		
		}
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));		
	}

	public function move() {
		$this->load->language('extension/translation');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/translation')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (isset($this->request->get['code'])) {
			$code = $this->request->get['code'];
		} else {
			$code = '';
		}
		
		$directory = ini_get('upload_tmp_dir') . '/lng-' . $code . '/2.0.0.x/';

		if (!is_dir($directory) || substr(str_replace('\\', '/', realpath($directory)), 0, strlen(ini_get('upload_tmp_dir'))) != str_replace('\\', '/', ini_get('upload_tmp_dir'))) {
			$json['error'] = $this->language->get('error_directory');
		}

		if (!$json) {
			// Get a list of files ready to upload
			$files = array();

			$path = array($directory . '*');

			while (count($path) != 0) {
				$next = array_shift($path);

				foreach ((array)glob($next) as $file) {
					if (is_dir($file)) {
						$path[] = $file . '/*';
					}

					$files[] = $file;
				}
			}

			foreach ($files as $file) {
				$destination = substr($file, strlen($directory));
				
				if (substr($destination, 0, 5) == 'admin') {
					$destination = DIR_APPLICATION . substr($destination, 6);
				}

				if (substr($destination, 0, 7) == 'catalog') {
					$destination = DIR_CATALOG . substr($destination, 7);
				}
				
				$json['success'] = $this->language->get('text_download');
				
				//$json['next'] = str_replace('&amp;', '&', $this->url->link('extension/translation/move', 'token=' . $this->session->data['token'] . '&code=' . $code, true));		
				
				if (is_file($file)) {
					copy($file, $destination);
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function db() {
		$this->load->language('extension/translation');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/translation')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (isset($this->request->get['code'])) {
			$this->load->model('localisation/language');
			
			$language_info = $this->model_localisation_language->getLanguageByCode($this->request->get['code']);

			if (!$language_info) {
				
			//	foreach () {
					
			//	}
				
				$this->model_localisation_language->addLanguage();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
	public function remove() {
		$this->load->language('extension/translation');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/translation')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (isset($this->request->get['code'])) {
			$code = $this->request->get['code'];
		} else {
			$code = '';
		}
		
		$directory = ini_get('upload_tmp_dir') . 'lng-' . $code;

		if (!is_dir($directory) || substr(str_replace('\\', '/', realpath($directory)), 0, strlen(DIR_UPLOAD)) != DIR_UPLOAD) {
			$json['error'] = $this->language->get('error_directory');
		}

		if (!$json) {
			// Get a list of files ready to upload
			$files = array();

			$path = array($directory);

			while (count($path) != 0) {
				$next = array_shift($path);

				// We have to use scandir function because glob will not pick up dot files.
				foreach (array_diff(scandir($next), array('.', '..')) as $file) {
					$file = $next . '/' . $file;

					if (is_dir($file)) {
						$path[] = $file;
					}

					$files[] = $file;
				}
			}

			rsort($files);
			
			foreach ($files as $file) {
				if (is_file($file)) {
					unlink($file);

				} elseif (is_dir($file)) {
					rmdir($file);
				}
			}

			if (is_dir($directory)) {
				rmdir($directory);
			}
						
			$json['success'] = $this->language->get('text_success');
		}
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));		
	}
	
	public function clear() {
		$this->load->language('extension/translation');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/translation')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			// Remove and language files
			$files = glob(ini_get('upload_tmp_dir') . '/lng-*.zip');
			
			foreach ($files as $file) {
				unlink($file);
			}
			
			// Remove and language directories
			$directories = glob(ini_get('upload_tmp_dir') . '/lng-*', GLOB_ONLYDIR);

			foreach ($directories as $directory) {
				// Get a list of files ready to upload
				$files = array();

				$path = array($directory);

				while (count($path) != 0) {
					$next = array_shift($path);

					// We have to use scandir function because glob will not pick up dot files.
					foreach (array_diff(scandir($next), array('.', '..')) as $file) {
						$file = $next . '/' . $file;

						if (is_dir($file)) {
							$path[] = $file;
						}

						$files[] = $file;
					}
				}

				rsort($files);

				foreach ($files as $file) {
					if (is_file($file)) {
						unlink($file);
					} elseif (is_dir($file)) {
						rmdir($file);
					}
				}

				if (is_dir($directory)) {
					rmdir($directory);
				}
			}
			
			$json['success'] = $this->language->get('text_clear');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}	
}