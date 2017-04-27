<?php
// Enable Error reporting
ini_set('display_errors', -1);
error_reporting(E_ALL);

// Disable caching
header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() - 3600));

// Get script base url
$baseURL = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
$baseURL = explode(basename(__FILE__), $baseURL);
$baseURL = current($baseURL);

// Disable execution time limit
set_time_limit(0);

class Installer
{
	/**
	 * CURL connection handle.
	 *
	 * @var   Resource
	 */
	protected $connection = null;

	/**
	 * Get contents of a file via URL (http)
	 *
	 * @param   String   $url   URL of a file.
	 *
	 * @return   String
	 */
	protected function getURLContents($url){
		if( function_exists('curl_init') ) {

			// Prepre CURL connection
			$this->prepareConnection($url);

			// Return response
			$buffer = curl_exec ($this->connection);

		} else {
			$options = $file_get_contents_options = array(
				'ssl' => array(
					"verify_peer" => false,
					"verify_peer_name" => false
				),
				'http' => array(
					'user_agent' => $_SERVER['HTTP_USER_AGENT']
				)
			);

			$buffer = file_get_contents(
				$url, false,
				stream_context_create($options)
			);
		}

		return $buffer;
	}

	/**
	 * Prepare CURL connection.
	 *
	 * @param   String   $url    URL to be used in connection.
	 * @param   String   $handle File handle to be used in connection.
	 */
	protected function prepareConnection($url = null, $handle = null){

		// Connection needs to be created
		if( !is_resource($this->connection) ) {

			// Initialise connection
			$this->connection = curl_init();

			// Configure CURL
			curl_setopt($this->connection, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->connection, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($this->connection, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		}

		// Set URL
		if( !is_null($url) ) {
			curl_setopt($this->connection, CURLOPT_URL, $url);
		}

		// Set File Handle
		if( !is_null($handle) ) {
			curl_setopt($this->connection, CURLOPT_TIMEOUT, 100);
			curl_setopt($this->connection, CURLOPT_FILE, $handle);
			curl_setopt($this->connection, CURLOPT_FOLLOWLOCATION, true);
		}
	}

	/**
	 * Download a file to local filesystem.
	 *
	 * @param type $url
	 * @param type $path
	 *
	 * @throws Exception
	 */
	protected function downloadFile($url, $path) {

		if( function_exists('curl_init') ) {

			// Create file handle
			$handle = fopen ($path, 'w+');

			// Prepare CURL connection
			$this->prepareConnection($url, $handle);

			// Run CURL
			curl_exec($this->connection);
			$error = curl_error($this->connection);
			if( !empty($error) ) {
				throw new Exception('(Curl) '.$error, 502);
			}

			// Close file handle
			fclose($handle);

			// Close CURL connection
			curl_close($this->connection);

		} else {

			$options = $file_get_contents_options = array(
				'ssl' => array(
					"verify_peer" => false,
					"verify_peer_name" => false
				),
				'http' => array(
					'user_agent' => $_SERVER['HTTP_USER_AGENT']
				)
			);

			file_put_contents(
				$path,
				file_get_contents(
					$url, false,
					stream_context_create($options)
				)
			);
		}
	}

	/**
	 * Downloads a selected installation packaged.
	 *
	 * @param	String	$url_zip	Download the installation package.
	 */
	public function downloadTask($url_zip) {
		if( file_exists(__DIR__.'/wordpress.zip') ) {
			return true;
		}
		// Download zip
		$this->downloadFile($url_zip, __DIR__.'/wordpress.zip');
	}

	/**
	 * Download package, unpack it, install and redirect to
	 * Wordpress installation page.
	 *
	 * @throws Exception
	 */
	public function prepareTask(){

		// Remove this script
		unlink(__FILE__);

		// Unpack
		$package = new ZipArchive;
		if ($package->open(__DIR__.'/wordpress.zip') === TRUE) {
			// Extract files to created directory
			for( $i=1, $ic=$package->numFiles; $i<$ic; $i++ ){

				// Get path
				$path = $package->getNameIndex($i);
				$info = pathinfo($path);

				// Create directory
				if( stripos($info['dirname'], '/') ) {
					$parts = explode('/',$info['dirname']);
					if( empty($info['extension']) ) {
						$dirname = __DIR__.'/'.implode('/', array_slice($parts, 1)).'/'.$info['basename'];
					} else {
						$dirname = __DIR__.'/'.implode('/', array_slice($parts, 1));
					}

				} else {
					$dirname = __DIR__;
				}

				// If we need to create this directory
				if ( !is_dir($dirname) ) {
					mkdir($dirname, 0755, true);
				}
				if( empty($info['extension']) ) {
					continue;
				} else {
					$buff = $package->getFromIndex($i);
					file_put_contents($dirname.'/'.$info['basename'], $buff);
				}
			}
			$package->close();

			// Remove package
			unlink(__DIR__.'/wordpress.zip');
		} else {
			throw new Exception('Cannot extract wordpress.zip', 502);
		}
	}

	/**
	 * Check Class requirements
	 *
	 * @throws Exception
	 */
	public function checkRequirements(){

		// Check if PHP can get remote contect
		if( !ini_get('allow_url_fopen') OR !function_exists('curl_init') ) {
			throw new Exception('This class requires <b>CURL</b> or <b>allow_url_fopen</b> to be enabled in PHP configuration.', 502);
		}

		// Check if server allow to extract zip files
		if( !class_exists('ZipArchive')) {
			throw new Exception('Class <b>ZipArchive</b> is not available in current PHP configuration.', 502);
		}

	}
}

// Create new Installer instance
$installer = new Installer;

// Run tasks
try {

	// First check if server meets requirements
	$installer->checkRequirements();

	// If this is install task
	if (isset($_GET['install'])) {
		$installer->downloadTask($_GET['install']);
	}
	if (isset($_GET['prepare'])) {
		$installer->prepareTask();
	}

} catch (Exception $e) {
	die('ERROR: '.$e->getMessage());
}

if( isset($_GET['install']) OR isset($_GET['prepare'])) {
	die('OK.');
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
		<base href="<?php echo $baseURL ?>" />
        <meta name="description" content="">
        <meta name="author" content="">
        <title>GetWordpress</title>

        <!-- Bootstrap core CSS -->
        <link href="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
        <link href="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap-theme.min.css" rel="stylesheet">

		<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
		<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/js/bootstrap.min.js"></script>

        <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
          <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->

		<style>
			.container{padding-top:30px;padding-bottom:30px}.windows8{position:relative;width:56px;height:56px;left:50%;top:50%;transform:translate(-50%, -50%)}.windows8 .wBall{position:absolute;width:53px;height:53px;opacity:0;transform:rotate(225deg);-o-transform:rotate(225deg);-ms-transform:rotate(225deg);-webkit-transform:rotate(225deg);-moz-transform:rotate(225deg);animation:orbit 5.4425s infinite;-o-animation:orbit 5.4425s infinite;-ms-animation:orbit 5.4425s infinite;-webkit-animation:orbit 5.4425s infinite;-moz-animation:orbit 5.4425s infinite}.windows8 .wBall .wInnerBall{position:absolute;width:7px;height:7px;background:#fff;left:0;top:0;border-radius:7px}.windows8 #wBall_1{animation-delay:1.186s;-o-animation-delay:1.186s;-ms-animation-delay:1.186s;-webkit-animation-delay:1.186s;-moz-animation-delay:1.186s}.windows8 #wBall_2{animation-delay:.233s;-o-animation-delay:.233s;-ms-animation-delay:.233s;-webkit-animation-delay:.233s;-moz-animation-delay:0.233s}.windows8 #wBall_3{animation-delay:.4765s;-o-animation-delay:.4765s;-ms-animation-delay:.4765s;-webkit-animation-delay:.4765s;-moz-animation-delay:0.4765s}.windows8 #wBall_4{animation-delay:.7095s;-o-animation-delay:.7095s;-ms-animation-delay:.7095s;-webkit-animation-delay:.7095s;-moz-animation-delay:0.7095s}.windows8 #wBall_5{animation-delay:.953s;-o-animation-delay:.953s;-ms-animation-delay:.953s;-webkit-animation-delay:.953s;-moz-animation-delay:0.953s}@keyframes orbit{0%{opacity:1;z-index:99;transform:rotate(180deg);animation-timing-function:ease-out}7%{opacity:1;transform:rotate(300deg);animation-timing-function:linear;origin:0}30%{opacity:1;transform:rotate(410deg);animation-timing-function:ease-in-out;origin:7%}39%{opacity:1;transform:rotate(645deg);animation-timing-function:linear;origin:30%}70%{opacity:1;transform:rotate(770deg);animation-timing-function:ease-out;origin:39%}75%{opacity:1;transform:rotate(900deg);animation-timing-function:ease-out;origin:70%}76%{opacity:0;transform:rotate(900deg)}100%{opacity:0;transform:rotate(900deg)}}@-o-keyframes orbit{0%{opacity:1;z-index:99;-o-transform:rotate(180deg);-o-animation-timing-function:ease-out}7%{opacity:1;-o-transform:rotate(300deg);-o-animation-timing-function:linear;-o-origin:0}30%{opacity:1;-o-transform:rotate(410deg);-o-animation-timing-function:ease-in-out;-o-origin:7%}39%{opacity:1;-o-transform:rotate(645deg);-o-animation-timing-function:linear;-o-origin:30%}70%{opacity:1;-o-transform:rotate(770deg);-o-animation-timing-function:ease-out;-o-origin:39%}75%{opacity:1;-o-transform:rotate(900deg);-o-animation-timing-function:ease-out;-o-origin:70%}76%{opacity:0;-o-transform:rotate(900deg)}100%{opacity:0;-o-transform:rotate(900deg)}}@-ms-keyframes orbit{0%{opacity:1;z-index:99;-ms-transform:rotate(180deg);-ms-animation-timing-function:ease-out}7%{opacity:1;-ms-transform:rotate(300deg);-ms-animation-timing-function:linear;-ms-origin:0}30%{opacity:1;-ms-transform:rotate(410deg);-ms-animation-timing-function:ease-in-out;-ms-origin:7%}39%{opacity:1;-ms-transform:rotate(645deg);-ms-animation-timing-function:linear;-ms-origin:30%}70%{opacity:1;-ms-transform:rotate(770deg);-ms-animation-timing-function:ease-out;-ms-origin:39%}75%{opacity:1;-ms-transform:rotate(900deg);-ms-animation-timing-function:ease-out;-ms-origin:70%}76%{opacity:0;-ms-transform:rotate(900deg)}100%{opacity:0;-ms-transform:rotate(900deg)}}@-webkit-keyframes orbit{0%{opacity:1;z-index:99;-webkit-transform:rotate(180deg);-webkit-animation-timing-function:ease-out}7%{opacity:1;-webkit-transform:rotate(300deg);-webkit-animation-timing-function:linear;-webkit-origin:0}30%{opacity:1;-webkit-transform:rotate(410deg);-webkit-animation-timing-function:ease-in-out;-webkit-origin:7%}39%{opacity:1;-webkit-transform:rotate(645deg);-webkit-animation-timing-function:linear;-webkit-origin:30%}70%{opacity:1;-webkit-transform:rotate(770deg);-webkit-animation-timing-function:ease-out;-webkit-origin:39%}75%{opacity:1;-webkit-transform:rotate(900deg);-webkit-animation-timing-function:ease-out;-webkit-origin:70%}76%{opacity:0;-webkit-transform:rotate(900deg)}100%{opacity:0;-webkit-transform:rotate(900deg)}}@-moz-keyframes orbit{0%{opacity:1;z-index:99;-moz-transform:rotate(180deg);-moz-animation-timing-function:ease-out}7%{opacity:1;-moz-transform:rotate(300deg);-moz-animation-timing-function:linear;-moz-origin:0}30%{opacity:1;-moz-transform:rotate(410deg);-moz-animation-timing-function:ease-in-out;-moz-origin:7%}39%{opacity:1;-moz-transform:rotate(645deg);-moz-animation-timing-function:linear;-moz-origin:30%}70%{opacity:1;-moz-transform:rotate(770deg);-moz-animation-timing-function:ease-out;-moz-origin:39%}75%{opacity:1;-moz-transform:rotate(900deg);-moz-animation-timing-function:ease-out;-moz-origin:70%}76%{opacity:0;-moz-transform:rotate(900deg)}100%{opacity:0;-moz-transform:rotate(900deg)}}.btn{background:#0074A2}.loader{background:#0074A2;position:absolute;top:0;left:0;width:0;height:0;overflow:hidden;opacity:0;transition:opacity 0.2s linear;z-index:9999}.loader.enabled{width:100%;height:100%;opacity:1}#loader-message{position:relative;top:50%;left:50%;transform:translate(-50%, 0px);color:#fff;display:inline-block;max-width:70%}
		</style>
		<script>
			$(document).ready(function(){

				// Getting versions list
				$('.loader').addClass('enabled');
				$.ajax({
					url: "https://api.github.com/repos/WordPress/WordPress/tags"
				}).done(function( data ) {
					$('.loader').removeClass('enabled');
					for(var i=0,ic=data.length,version=data[0]; i<ic; i++, version = data[i]) {
						var $element = $('<option value="'+version.zipball_url+'">'+version.name+'</option>');
						if( i===0 ) {
							$element.attr('selected','true');
							$('#latest-version').append($element);
						} else {
							$('#other-versions').append($element);
						}
					}
				});

				$('form .btn.btn-primary').click(function(){

					$('#loader-message').text('Downloading package.');
					$('.loader').addClass('enabled');

					var $url = $('#version-select').val();
					$.ajax({
						'url':'',
						async: false,
						data: {
							'install':$url
						}
					}).done(function(data){
						if( data!=='OK.' ) {
							alert('Error while downloading: '+data);
						} else {
							$('#loader-message').text('Preparing installation.');
							$.ajax({
								'url':'?prepare',
								async: false
							}).done(function(data){
								if( data!=='OK.' ) {
									alert('Error while preparing: '+data);
								} else {
									$('#loader-message').text('Redirecting to installer.');
									window.location.reload();
								}
							});

						}
					});
					return false;
				});
			});
		</script>
    </head>

    <body>

        <div class="container">
            <div class="jumbotron text-center">
                <h1>GetWordpress <small>v1.0.0</small></h1>
                <p class="lead">Just a crazy fast script to download and prepare Wordpress installation.</p>
				<form action="<?php echo $baseURL.basename(__FILE__) ?>" method="get">
					<div class="input-group">
						<select class="form-control" name="install" id="version-select">
							<optgroup label="Latest version" id="latest-version"></optgroup>
							<optgroup label="Other versions" id="other-versions"></optgroup>
						</select>
						<span class="input-group-btn">
							<input type="submit" class="btn btn-primary" value="Install"/>
						</span>
					</div><!-- /input-group -->
				</form>
            </div>

            <div class="row marketing">
                <div class="col-lg-6">
                    <h4>How it works</h4>
                    <p>This script downloads selected release from <a href="https://github.com/wordpress/wordpress/tags">Wordpress Github repository</a>, unpacks it, creates <code>.htaccess</code>, removes itself and then redirects you to Wordpress installation. In short: just select version and click install to run Wordpress installer.</p>
                </div>

                <div class="col-lg-6">
                    <h4>Warning</h4>
                    <p>This script is free for everyone. Im not responsible for any damage it can make. Remember to always have a copy of files you have on this server.</p>

                    <h4>License</h4>
                    <p>This script is released under <a href="http://www.gnu.org/licenses/gpl-3.0.txt">GNU/GPL 3.0 license</a>. Free for commercial and non-commercial usage.</p>
                </div>
            </div>

            <footer class="footer">
                <p>&copy; <?php echo date('Y') ?> <a href="http://www.bestproject.pl">BestProject</a></p>
            </footer>

        </div>
		<div class="loader">
			<div class="windows8">
				<div class="wBall" id="wBall_1">
					<div class="wInnerBall"></div>
				</div>
				<div class="wBall" id="wBall_2">
					<div class="wInnerBall"></div>
				</div>
				<div class="wBall" id="wBall_3">
					<div class="wInnerBall"></div>
				</div>
				<div class="wBall" id="wBall_4">
					<div class="wInnerBall"></div>
				</div>
				<div class="wBall" id="wBall_5">
					<div class="wInnerBall"></div>
				</div>
			</div>
			<div id="loader-message">Loading versions list</div>
		</div>
    </body>
</html>
