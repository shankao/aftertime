<script type="text/javascript">
/*                            
	var suites_sid = new Array();
	var sid_regex = new RegExp(/<!-- lolailo (.*) -->/);
*/
	$(".testinfo").
	live("click", function(event) {
	        event.preventDefault();
	        var testname = $(this).attr("testname");
	        $("#test_" + testname + " label").toggle();
	});
                                
	$(".testiframe").
	live("click", 
	        function(event) {
	                event.preventDefault();
	                var testname = $(this).attr("testname");
	                
	                if ($("#test_" + testname + " textarea").length > 0) {

	                        var output = $("#test_" + testname + " .output").text();
                                var expected = $("#test_" + testname + " .expected").text();
                                $("#test_" + testname + " .output").
                                	replaceWith('<iframe type="text/html" id="' + testname + '_output" class="output"></iframe>');
                                window[testname + "_output"].document.test = output;
                                window[testname + "_output"].document.documentElement.innerHTML = output;

                                $("#test_" + testname + " .expected").
	                                replaceWith('<iframe type="text/html" id="' + testname + '_expected" class="expected"></iframe>');
                                window[testname + "_expected"].document.test = expected;
                                window[testname + "_expected"].document.documentElement.innerHTML = expected;
			} else {
                        	var output = window[testname + "_output"].document.test;
                                var expected = window[testname + "_expected"].document.test;
                                $("#test_" + testname + " .output").
                                	replaceWith('<textarea class="output" id="' + testname + '_output" readonly="readonly"></textarea>');
                                $("#test_" + testname + " .output").text(output);

                                $("#test_" + testname + " .expected").
                	                replaceWith('<textarea class="expected" id="' + testname + '_expected" readonly="readonly"></textarea>');
                                $("#test_" + testname + " .expected").text(expected);
                        }
       		}
	);
</script>
