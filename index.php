<?php
/*
Name: AWS
Description: Store uploaded data to AWS S3
Version: 1.0
Compatible: 2.0.5
*/

class lumise_addon_aws extends lumise_addons {
	
	function __construct() {
		
		global $lumise;
		
		/*
		*	Access core js via your JS function name
		*/
		
		$this->access_corejs('lumise_addon_aws');
		
		$lumise->add_action('editor-footer', array(&$this, 'editor_footer'));

		
	}

	public function editor_footer() {
		
		global $lumise;

		if (!$this->is_backend()) {
			echo '<script type="text/javascript">var aws_bucket_info = {
				aws_bucket_name : "' . $lumise->get_option('aws_bucket_name') . '",
				access_key_id : "' . $lumise->get_option('access_key_id') . '",
				access_secret_key_id : "' . $lumise->get_option('access_secret_key_id') . '",
				region : "' . $lumise->get_option('region') . '",
				folder : "' . $lumise->get_option('folder') . '",
			};</script>';
			echo '<script type="text/javascript" src="'.$this->get_url('assets/js/aws.js?ver=1').'"></script>';
		}
	}
	
	public function settings() {
		return array(
			array(
				'type' => 'input',
				'name' => 'aws_bucket_name',
				'desc' => 'Enter your bucket name',
				'label' => 'Bucket Name',
				'default' => 'bucket-name'
			),
			array(
				'type' => 'input',
				'name' => 'access_key_id',
				'desc' => 'Enter Public Key',
				'label' => 'AWS Public Key',
				'default' => ''
			),
			array(
				'type' => 'input',
				'type_input' => 'password',
				'name' => 'access_secret_key_id',
				'desc' => 'Enter Secret Key',
				'label' => 'AWS Secret Key',
				'default' => ''
			),
			array(
				'type' => 'input',
				'name' => 'region',
				'desc' => 'Enter Region',
				'label' => 'Region',
				'default' => ''
			),
			array(
				'type' => 'input',
				'name' => 'folder',
				'desc' => 'Enter Folder',
				'label' => 'Folder',
				'default' => ''
			),
		);
		
	}
}
