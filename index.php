<?php
/*
Name: AWS
Description: Store uploaded data to AWS S3
Version: 1.0
Compatible: 2.0.5
*/

require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class lumise_addon_aws extends lumise_addons
{
	private $s3Client;
	function __construct()
	{

		global $lumise;

		$this->access_corejs('lumise_addon_aws');
		$lumise->add_action('editor-footer', array(&$this, 'editor_footer'));

		// Register REST API route for file upload
		add_action('rest_api_init', array($this, 'register_api_route'));

		$lumise->add_action('store-cart', array(&$this, 'store_cart'));


		// Initialize AWS S3 client when the class is instantiated
		$this->initialize_s3();
	}

	public function editor_footer()
	{

		global $lumise;

		if (!$this->is_backend()) {
			echo '<script type="text/javascript">var aws_bucket_info = true;</script>';
			echo '<script type="text/javascript" src="' . $this->get_url('assets/js/aws.js?ver=1') . '"></script>';
		}
	}

	public function settings()
	{
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
				'default' => 'ap-southeast-2'
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

	public function register_api_route()
	{
		register_rest_route('lumise/v1', '/s3-upload', array(
			'methods' => 'POST',
			'callback' => array($this, 'handle_s3_upload'),
			'permission_callback' => '__return_true' // Adjust for security as needed
		));

		register_rest_route('lumise/v1', '/s3-get-images/(?P<order_id>\d+)/(?P<cart_id>[a-zA-Z0-9_-]+)', array(
			'methods' => 'GET',
			'callback' => array($this, 'get_order_images'),
			'args' => array(
					'order_id' => array(
							'required' => true,
							'validate_callback' => function($param) {
									return is_numeric($param);
							}
					),
					'cart_id' => array(
							'required' => true,
							'validate_callback' => function($param) {
									return preg_match('/^[a-zA-Z0-9_-]+$/', $param);
							}
					)
			),
			'permission_callback' => '__return_true' // Adjust for security as needed
	));
	
	}

	public function get_order_images($request)
	{
		global $lumise;
		$order_id = $request['order_id'];
		$cart_id = $request['cart_id']; // This is the new parameter
		$bucket = $lumise->get_option('aws_bucket_name');
		$path_prefix = 'LUMISE_ORDERS/' . date('Y/m', time()) . "/order#{$order_id}/";

		try {
			// List objects under the order path
			$objects = $this->s3Client->listObjectsV2([
				'Bucket' => $bucket,
				'Prefix' => $path_prefix,
			]);

			$urls = [];
			if (isset($objects['Contents'])) {
				foreach ($objects['Contents'] as $object) {
					$key = $object['Key'];
					// Check if the current folder or key contains the product ID
					if (strpos($key, "cart#{$cart_id}") !== false) {
						$urls[] = $this->s3Client->getObjectUrl($bucket, $key);
					}
				}
			}

			return rest_ensure_response($urls);
		} catch (AwsException $e) {
			return new WP_Error('list_objects_error', $e->getMessage(), array('status' => 500));
		}
	}

	public function handle_s3_upload($request)
	{
		global $lumise;
		if (empty($_FILES['file'])) {
			return new WP_Error('no_file', 'No file provided', array('status' => 400));
		}
		$aws_region = (isset($lumise->get_option('region')) && $lumise->get_option('region') != '') ? $lumise->get_option('region') : 'ap-southeast-2';

		$file = $_FILES['file'];
		// AWS S3 Client setup
		$s3 = new S3Client([
			'version' => 'latest',
			'region' => $aws_region ,
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

	// Function to initialize AWS S3 client using the SDK
	private function initialize_s3()
	{
		global $lumise;
		$aws_access_key = $lumise->get_option('access_key_id');
		$aws_secret_key = $lumise->get_option('access_secret_key_id');
		$aws_region = (isset($lumise->get_option('region')) && $lumise->get_option('region') != '') ? $lumise->get_option('region') : 'ap-southeast-2';

		// $aws_bucket = $lumise->get_option('aws_bucket');

		// Instantiate S3 client with AWS credentials
		$this->s3Client = new S3Client([
			'version' => 'latest',
			'region' => $aws_region,
			'credentials' => [
				'key'    => $aws_access_key,
				'secret' => $aws_secret_key,
			],
			'http'    => [
				'verify' => false
			]
		]);
	}

	public function store_cart($order_id = 0)
	{

		global $lumise;

		$order_id = (int)$order_id;  // Cast the order ID to an integer.
		// Retrieve all items associated with the order.
		$items = $lumise->db->rawQuery("SELECT `product_id`, `product_base`, `qty`, `print_files`, `screenshots`, `cart_id` FROM `{$lumise->db->prefix}order_products` WHERE `order_id`={$order_id}");

		// If no items are found, exit the function.
		if (count($items) === 0)
			return;

		$bucket = $lumise->get_option('aws_bucket_name');
		// $folder = $lumise->get_option('dropbox_orders_folder');

		$path = 'LUMISE_ORDERS/' . date('Y/m', time());

		$stt = 0;

		foreach ($items as $item) {

			$item_path = $path . "/order#" . $order_id . '/item#' . $stt . ' - product#' . (!empty($item['product_id']) ? $item['product_id'] : $item['product_base']) . ' - cart#' . $item['cart_id'] . ' - (qty ' . $item['qty'] . ')';
			$stt++;

			if (!empty($item['print_files'])) {
				$files = @json_decode($item['print_files']);

				if (count($files) > 0) {
					foreach ($files as $file) {

						$file_path = WP_CONTENT_DIR . '/uploads/lumise_data/orders/' . date('Y/m', time()) . '/' . basename($file);
						$unique_key = $item_path . '/stages/' . basename($file);
						// Upload the file to the S3 bucket
						$this->upload_to_s3($bucket, $unique_key, $file_path);
					}
				}
			}


			if (!empty($item['screenshots'])) {
				$files = @json_decode($item['screenshots']);

				if (count($files) > 0) {
					foreach ($files as $file) {

						$file_path = WP_CONTENT_DIR . '/uploads/lumise_data/orders/' . date('Y/m', time()) . '/' . basename($file);
						$unique_key = $item_path . '/screenshots/' . basename($file);
						// Upload the file to the S3 bucket
						$this->upload_to_s3($bucket, $unique_key, $file_path);
					}
				}
			}
		}
	}

	// Function to handle the S3 upload process
	private function upload_to_s3($bucket, $key, $file_path)
	{
		try {
			// Upload the file to the specified bucket and key (path)
			$this->s3Client->putObject([
				'Bucket' => $bucket,
				'Key'    => $key,
				'SourceFile' => $file_path,
				'ACL'    => 'public-read', // Optional: Make the file publicly accessible
			]);

			echo "File successfully uploaded to {$bucket}/{$key}";
		} catch (AwsException $e) {
			// Output error message if upload fails
			echo "Error uploading to S3: " . $e->getMessage();
		}
	}
}

new lumise_addon_aws();
