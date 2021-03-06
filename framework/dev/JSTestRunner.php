<?php
/**
 * Controller that executes QUnit tests via jQuery.
 * Finds all htm/html files located in <yourmodule>/javascript/tests
 * and includes them as iFrames.
 * 
 * To create your own tests, please use this template:
 * <code>
 * <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
 * <html>
 * <head>
 * 	<script src="http://code.jquery.com/jquery-latest.js"></script>
 *  <link rel="stylesheet" href="http://dev.jquery.com/view/trunk/qunit/testsuite.css" type="text/css" media="screen" />
 * 	<script>
 * 	$(document).ready(function(){
 * 		test("test my feature", function() {
 * 			ok('mytest');
 * 		});
 * 	});
 *   </script>
 * </head>
 * <body>
 * <script type="text/javascript" src="http://jqueryjs.googlecode.com/svn/trunk/qunit/testrunner.js"></script>
 *  <h1>My Test Name</h1>
 *  <h2 id="banner"></h2>
 *  <h2 id="userAgent"></h2>
 *  <ol id="tests"></ol>
 *  <div id="main"></div>
 * </body>
 * </html>
 * </code>
 * 
 * @package framework
 * @subpackage testing
 */
class JSTestRunner extends Controller {
	/** @ignore */
	private static $default_reporter;
	
	static $url_handlers = array(
		'' => 'browse',
		'$TestCase' => 'only',
	);
	
	static $allowed_actions = array(
		'index',
		'all',
		'browse',
		'only'
	);
	
	/**
	 * Override the default reporter with a custom configured subclass.
	 *
	 * @param string $reporter
	 */
	static function set_reporter($reporter) {
		if (is_string($reporter)) $reporter = new $reporter;
		self::$default_reporter = $reporter;
	}
	
	function init() {
		parent::init();
		
		if(Director::is_cli()) {
			echo "Error: JSTestRunner cannot be run in CLI mode\n";
			die();
		}
		
		if (!self::$default_reporter) self::set_reporter('DebugView');
	}
	
	public function Link() {
		return Controller::join_links(Director::absoluteBaseURL(), 'dev/jstests/');
	}
	
	/**
	 * Run all test classes
	 */
	function all() {
		$this->runTests(array_keys($this->getAllTestFiles()));
	}
	
	/**
	 * Browse all enabled test cases in the environment
	 */
	function browse() {
		self::$default_reporter->writeHeader();
		echo '<div class="info">';
		echo '<h1>Available Tests</h1>';
		echo '</div>';
		echo '<div class="trace">';
		$tests = $this->getAllTestFiles();
		echo "<h3><a href=\"" . $this->Link() . "all\">Run all " . count($tests) . " tests</a></h3>";
		echo "<hr />";
		foreach ($tests as $testName => $testFilePath) {
			echo "<h3><a href=\"" . $this->Link() . "$testName\">Run $testName</a></h3>";
		}
		echo '</div>';
		self::$default_reporter->writeFooter();
	}
		
	/**
	 * Run only a single test class
	 */
	function only($request) {
		$test = $request->param('TestCase');
		
		if ($test == 'all') {
			$this->all();
		} else {
			$allTests = $this->getAllTestFiles();
			if(!array_key_exists($test, $allTests)) {
				user_error("TestRunner::only(): Invalid TestCase '$className', cannot find matching class", E_USER_ERROR);
			}
			
			$this->runTests(array($test));
		}
	}

	function runTests($tests) {
		$this->setUp();

		self::$default_reporter->writeHeader("SilverStripe JavaScript Test Runner");
		self::$default_reporter->writeInfo("All Tests", "Running test cases: " . implode(", ", $tests));

		foreach($tests as $test) {
			// @todo Integrate output in DebugView
			$testUrl = $this->urlForTestCase($test);
			if(!$testUrl) user_error('JSTestRunner::runTests(): Test ' . $test . ' not found', E_USER_ERROR);
			$absTestUrl = Director::absoluteBaseURL() . $testUrl;
			
			echo '<iframe src="' . $absTestUrl . '" width="800" height="300"></iframe>';
		}
				
		$this->tearDown();
	}
	
	function setUp() {
	}
	
	function tearDown() {
	}
	
	protected function getAllTestFiles() {
		$testFiles = array();
		
		$baseDir = Director::baseFolder();
		$modules = scandir($baseDir);
		foreach($modules as $moduleFileOrFolder) {
			if(
				$moduleFileOrFolder[0] == '.' 
				|| !@is_dir("$baseDir/$moduleFileOrFolder") 
				|| !file_exists("$baseDir/$moduleFileOrFolder/_config.php")
			) {
				continue;
			}

			$testDir = "$baseDir/$moduleFileOrFolder/tests/javascript";
			if(@is_dir($testDir)) {
				$tests = scandir($testDir);
				foreach($tests as $testFile) {
					$testFileExt = pathinfo("$testDir/$testFile", PATHINFO_EXTENSION);
					if(!in_array(strtolower($testFileExt),array('htm','html'))) continue;
					$testFileNameWithoutExt = substr($testFile, 0,-strlen($testFileExt)-1);
					$testUrl = Director::makeRelative("$testDir/$testFile");
					$testUrl = substr($testUrl, 1);
					// @todo Limit to html extension with "Test" suffix
					$testFiles[$testFileNameWithoutExt] = $testUrl;
				}
			}
		}

		return $testFiles;
	}
	
	/**
	 * Returns the URL for a test case file.
	 * 
	 * @return string
	 */
	protected function urlForTestCase($testName) {
		$allTests = $this->getAllTestFiles();
		return (array_key_exists($testName, $allTests)) ? $allTests[$testName] : false;
	}
}
