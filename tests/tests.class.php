<?php
/* 
Unit test framework with jQuery and chained AJAX calls
Code by shankao@gmail.com. Made in Dahab (Egypt) mar-2011 while smoking shisha and drinking beer. Use with caution
*/

class tests {
	var $template;

	function test_html ($testname) { 
		static $testnumber = 0;
		$tag = "{$testname}_{$testnumber}";
		?>

		<div class="test" id="test_<?php echo $tag; ?>">
	        	<div>
				<a class="testinfo" testname="<?php echo $tag; ?>" href="#"><?php 
					if (file_exists("tests/data/{$testname}.name"))
						echo file_get_contents("tests/data/{$testname}.name");
					else
						echo $testname;
				?></a>:
				<span class="result"></span>
				<label style="display: none;"><a class="testiframe" testname="<?php echo $tag; ?>" href="#">(Show as iframe)</a></label>
			</div>
        		<label style="display: none;">Test output:<textarea class="output" id="<?php echo $tag; ?>_output" readonly="readonly"></textarea></label>
        		<label style="display: none;">Expected:<textarea class="expected" id="<?php echo $tag; ?>_expected" readonly="readonly"></textarea></label>
		</div><?php
		$testnumber++;
	}

	function test_events($testname, $prev = null, $next = null) {
		static $testnumber = 0;
		$tag = "{$testname}_{$testnumber}";

		if (!file_exists("tests/data/{$testname}.test")) { ?>
			$("#test_<?php echo $tag; ?> .result").
				addClass("testfailed").
				text("NOT FOUND");
			<?php
		} else { 
			if (isset($prev)) {  ?>
				$("#test_<?php echo $tag; ?>").
				live("run", function() { <?php
			}

			$test = file("tests/data/{$testname}.test", FILE_IGNORE_NEW_LINES);
			$testurl = $test[0];	// Always the first line
			$testdata = null;
                        for ($i = 1; isset($test[$i]); $i++) {
                        	$testdata .= htmlspecialchars($test[$i]);
                        }
			echo "var testurl = $testurl;";
			echo 'var testdata = new Object();';
			if (isset($testdata)) echo "testdata = $testdata;"; 

			/*
			// Attach the SID to the test URL
			for (i in suites_sid) {
				if (suites_sid[i].suite == "<?php echo $suite; ?>") {
					testdata.PHPSESSID = suites_sid[i].value;
					alert("Will run <?php echo $testname; ?>: " + testdata.PHPSESSID);
					break;
				}
			}
			*/
			?>

		      	$.post(
				testurl,
				testdata,
                		function(result) {
                        		var expected = "<?php
					$out = null;
                                	foreach (file("tests/data/{$testname}.out", FILE_IGNORE_NEW_LINES) as $line) {
						$line = str_replace('\\', '\\\\', $line);
						if ($out) $out .= "\\n";
                                        	$out .= htmlspecialchars($line);
					}
					echo str_replace('v\\', 'v\\\\', $out);	// Fixes a special case in the head section
                        		?>";
					expected = htmlspecialchars_decode(expected);

					$("#test_<?php echo $tag; ?> .output").text(result);
       	      	           		$("#test_<?php echo $tag; ?> .expected").text(expected);

	                        	if (result == expected) {
       	                         		$("#test_<?php echo $tag; ?> .result").
							addClass("testok").
							text("OK");
                        		} else {
                                		$("#test_<?php echo $tag; ?> .result").
							addClass("testfailed").
							text("FAILED");
                        		}

					<?php 
					/*
					// Search for a session id and store it
					if ($next) { ?>
						if ((match = sid_regex.exec(result)) != null) {
							$("#test_<?php echo $testname; ?> .result").
								append(" (SID: " + match[1] + ")");

							var sid = new Object();
							sid.suite = "<?php echo $suite; ?>";
							sid.value = match[1];
							suites_sid.push(sid);
						} <?php
					}
					*/

		                        if (isset($next)) {  
						$tmp = $testnumber + 1; ?>
                                		$("#test_<?php echo "{$next}_{$tmp}"; ?>").trigger("run"); <?php
		                        }  ?>
                		}
			); <?php

	                if (isset($prev)) {  ?>
        	        	}); <?php // prev. live function close 
			}
        	} 
		$testnumber++;
	}

	function testsuite ($suite) {
		require_once 'tests/test_controls.php';

		?>
		<h2 class="testsuite">
			<?php
                        if (file_exists("tests/data/{$suite}.suitename"))
                        	echo file_get_contents("tests/data/{$suite}.suitename");
                        else
                        	echo "Testsuite $suite";
                       	?>
		</h2>
		<?php
		$tests = file("tests/data/{$suite}.suite", FILE_IGNORE_NEW_LINES);

		for($i = 0; $i < count($tests); $i++) {
			$this->test_html($tests[$i]);
		}

		echo '<script type="text/javascript">';
		for($i = 0; $i < count($tests); $i++) {
			$this->test_events(
				$tests[$i],
				isset($tests[$i-1])? $tests[$i-1] : null,
				isset($tests[$i+1])? $tests[$i+1] : null
			);
		}
		echo '</script>';
	}

	function backupDB($filename) {
		global $config;
		$cmd = "mysqldump {$config->database->dbname} -u {$config->database->user} -p{$config->database->password} > tests/dumps/$filename";
		echo "$cmd\n";
		system($cmd, $out);
		echo $out;
	}

	function restoreDB($filename) {
		global $config;
		$cmd = "mysql {$config->database->dbname} -u {$config->database->user} -p{$config->database->password} < tests/dumps/$filename";
		echo "$cmd\n";
		system($cmd, $out);
		echo $out;
	}

	function init() {
		global $userparams;
		if ($userparams->username != 'test') {
			echo "Please log in to access this section:";
			include 'templates/loginform.php';
			$this->template = false;
		}
	
		if (isset($_GET['backup'])) {
			$this->backupDB($_GET['filename']);
			$this->template = false;
		} else if (isset($_GET['restore'])) {
			$this->restoreDB($_GET['filename']);
			$this->template = false;
		}
	}
}
?>
