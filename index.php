<?php
/*
Name: AWS
Description: Store uploaded data to AWS S3
Version: 1.0
Compatible: 2.0.5
*/

require 'vendor/autoload.php';
use Aws\S3\S3Client;
class lumise_addon_aws extends lumise_addons {

    function __construct() {
        
        global $lumise;

        $this->access_corejs('lumise_addon_aws');
        $lumise->add_action('editor-footer', array(&$this, 'editor_footer'));

        // Register REST API route for file upload
        add_action('rest_api_init', array($this, 'register_api_route'));
    }

    public function editor_footer() {
        
        global $lumise;

        if (!$this->is_backend()) {
            echo '<script type="text/javascript">var aws_bucket_info = true;</script>';
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

    public function register_api_route() {
        register_rest_route('lumise/v1', '/s3-upload', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_s3_upload'),
            'permission_callback' => '__return_true' // Adjust for security as needed
        ));
    }

    public function handle_s3_upload($request) {
			global $lumise;
        if (empty($_FILES['file'])) {
            return new WP_Error('no_file', 'No file provided', array('status' => 400));
        }

        $file = $_FILES['file'];

        

        // AWS S3 Client setup
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => $lumise->get_option('region'),
            'credentials' => [
                'key' => $lumise->get_option('access_key_id'),
                'secret' => $lumise->get_option('access_secret_key_id'),
						],
						'http'    => [
							'verify' => false
					]
        ]);

        $bucket = $lumise->get_option('aws_bucket_name');
        $folder = $lumise->get_option('folder') ? $lumise->get_option('folder') . '/' : '';
        $key = $folder . basename($file['name']);

        try {
            $result = $s3->putObject([
                'Bucket' => $bucket,
                'Key' => $key,
                'SourceFile' => $file['tmp_name'],
                'ACL' => 'public-read', // Adjust as needed
                'ContentType' => $file['type']
            ]);

            return rest_ensure_response(array('url' => $result['ObjectURL']));

        } catch (Exception $e) {
            return new WP_Error('upload_error', $e->getMessage(), array('status' => 500));
        }
    }
}

new lumise_addon_aws();
