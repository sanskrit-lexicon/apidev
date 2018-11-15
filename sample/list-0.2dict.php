<?php
 /* Similar to list-0.2.php, but TWO dictionaries
   //sanskrit-lexicon.uni-koeln.de/scans/awork/apidev/sample/list-0.2dict.php?dict1=pwg&key1=darh&dict2=mw&key2=dfh&input=iast&output=iast
 */
// Report all errors except E_NOTICE  (also E_WARNING?)
error_reporting(E_ALL & ~E_NOTICE);
/* See phpinit
 $dict1 = $_GET['dict1'];
 $dict2 = $_GET['dict2'];
 $key = $_GET['key'];
 $accent= $_GET['accent'];
 $input = $_GET['input'];
 $output = $_GET['output'];
*/
?>
<!DOCTYPE html> <!-- html5 -->
<html>
<head>
<META charset="UTF-8">
<title>list-0.2dict</title>
<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.css">
<!-- links to jquery, using CDNs -->
<script type="text/javascript" src="//code.jquery.com/jquery-2.1.4.min.js"></script>

<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery-cookie/1.4.1/jquery.cookie.min.js"></script>
<!-- jquery-ui is used -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
<script type="text/javascript" src="//www.sanskrit-lexicon.uni-koeln.de/scans/awork/apidev/sample/dictnames.js"></script>
<script type="text/javascript" src="//www.sanskrit-lexicon.uni-koeln.de/scans/awork/apidev/sample/cookieUpdate.js"></script>

<style>
body {
 color: black; background-color:#DBE4ED;
 /*font-size: 14pt; */
}

#dataframe1 {
 color: black; background-color: white;
 width: 600px;
 height: 600px;
 /* resize doesn't work on Firefox. 
  On Chrome, the size may be increased, but not decreased*/
 resize: both;
 /*overflow: auto; */
}
#dataframe2 {
 color: black; background-color: white;
 width: 600px;
 height: 600px;
 left: 650px;
 /* resize doesn't work on Firefox. 
  On Chrome, the size may be increased, but not decreased*/
 resize: both;
 /*overflow: auto; */
}

#accentdiv,#outputdiv,#inputdiv,#dictionary1,#dictionary2 input,label {display:block;}

#preferences {
 position:absolute;
 left:100px;
 top: 15px;
}
#preferences td {
 /*border:1px solid black;*/
 background-color:white;
 padding-left:5px;
 padding-right:5px;
 text-align:center;
}
#citationdiv{
 padding-bottom:5px;
}
#key2 {
 /*left: 650px; */
}

#correction {

 padding-left: 130px;
}
</style>
<script> 
 // Jquery
$(document).ready(function() {
 $('#key1').keypress(function (e) {
  if(e.which == 13)  // the enter key code
   {e.preventDefault();
    listDisplay(1);
   }
 }); // end keypress
 $('#key2').keypress(function (e) {
  if(e.which == 13)  // the enter key code
   {e.preventDefault();
    listDisplay(2);
   }
 }); // end keypress
 $('#input,#output,#dict,#accent').change(function(event) {
  cookieUpdate(true);   
 });
 $('#dict').change(function(event) {
  changeCorrectionHref();
 });
 changeCorrectionHref = function () {
  var dict = $('#dict').val();
  var url = "/php/correction_form.php?dict=" + dict;
  $('#correction').attr('href',url);
 };

 listDisplay = function (idict) {
  var keyid = '#key' + idict;
  var dictid = '#dict' + idict;
  var dataframeid = '#dataframe' + idict;
  var key = $(keyid).val();
  var dict = $(dictid).val();
  var input = $('#input').val();
  var output = $('#output').val();
  var accent = $('#accent').val();
  //console.log('listDisplay: accent value=',accent);

  // TODO: check for valid inputs before ajax call
  var urlbase="//www.sanskrit-lexicon.uni-koeln.de/scans/awork/apidev/listview.php";
  var url =  urlbase +  
   "?key=" +escape(key) + 
   "&output=" +escape(output) +
   "&dict=" + escape(dict) +
   "&accent=" + escape(accent) +
   "&input=" + escape(input);
    //jQuery("#disp").html(""); // clear output
  //console.log('listDisplay. idict=',idict,' , url=',url);
  jQuery(dataframeid).attr("src",url);

 }; // listDisplay
 
cookieUpdate = CologneDisplays.dictionaries.cookieUpdate;
cookieUpdate(false);  // for initializing cookie
changeCorrectionHref();  // initialize now that #dict is set.
$("#dict1").autocomplete( { 
  source : CologneDisplays.dictionaries.dictshow,
  autoFocus: true,
 }); // end autocomplete dictionary
$("#dict2").autocomplete( { 
  source : CologneDisplays.dictionaries.dictshow,
  autoFocus: true,
 }); // end autocomplete dictionary

/* This is based on the example at
https://jqueryui.com/autocomplete/#remote-jsonp
and is required to avoid cross-domain problems.  The server program also
requires some code for this purpose.
*/
$("#key1").autocomplete({
  source: function(request,response) {
   $.ajax({
   url:"//www.sanskrit-lexicon.uni-koeln.de/scans/awork/apidev/getsuggest.php",
   datatype:"jsonp",
   data: {
    //q: request.term
    term: request.term,
    dict: $('#dict1').val(),
    input: $('#input').val()
   },
   success: function(data) {
    response(data);  // 'response' is passed in as source argument
   }
   }); // ajax
  },
  delay : 500, // 500 ms delay
  minLength : 2, // user must type at least 2 characters
  select: function(event,ui) {
   if (ui.item) {
   $("#key1").val(ui.item.value);
    listDisplay(1);
   }
  },
  autoFocus: true,
}); //key1-autocomplete

$("#key2").autocomplete({
  source: function(request,response) {
   $.ajax({
   url:"//www.sanskrit-lexicon.uni-koeln.de/scans/awork/apidev/getsuggest.php",
   datatype:"jsonp",
   data: {
    //q: request.term
    term: request.term,
    dict: $('#dict2').val(),
    input: $('#input').val()
   },
   success: function(data) {
    response(data);  // 'response' is passed in as source argument
   }
   }); // ajax
  },
  delay : 500, // 500 ms delay
  minLength : 2, // user must type at least 2 characters
  select: function(event,ui) {
   if (ui.item) {
   $("#key2").val(ui.item.value);
    listDisplay(2);
   }
  },
  autoFocus: true,
}); //key1-autocomplete


 phpinit_helper = function(name,val){
  if (val == ''){return;}
  if (name == 'accent') { //val should be yes or no. Case not important
   val = val.toLowerCase();
  }
  /* 01-03-2018 */
  if (val == 'iast') {val = 'roman';}
  $('#' + name).val(val);
  //console.log("phpinit_helper: change #",name,"to",val);
 };
 phpinit = function() {
  var names = ['key1','key2','dict1','dict2','input','output','accent'];
  var phpvals=[ // same order as names
  "<?php echo $_GET['key1']?>",
  "<?php echo $_GET['key2']?>",
  "<?php echo $_GET['dict1']?>",
  "<?php echo $_GET['dict2']?>",
  "<?php echo $_GET['input']?>",
  "<?php echo $_GET['output']?>",
  "<?php echo $_GET['accent']?>"];
  var i,name,phpval;
  for(i=0;i<names.length;i++) {
   phpinit_helper(names[i],phpvals[i]);
  }
  // If key1 is provided, generate display for it
  if($('#key1').val() != '') {
   listDisplay(1);
  }
  // If key2 is provided, generate display for it
  if($('#key2').val() != '') {
   listDisplay(2);
  }
 };
 phpinit();
}); // end ready
 </script>
<script> // see MWScan/2014/web/webtcdev/main_webtc.js
</script>
</head>
<body>
 <div id="logo">
     <a href="//www.sanskrit-lexicon.uni-koeln.de/">
      <img id="unilogo" src="//www.sanskrit-lexicon.uni-koeln.de/images/cologne_univ_seal.gif"
           alt="University of Cologne" width="60" height="60" 
           title="Cologne Sanskrit Lexicon"/>
      </a>
 </div>

<table id="preferences">
<tr>
<!--
<td>
 <div id="dictionary1">
 <label for="dict1code">dictionary1</label>
 <input type="text" name="dict1code" size="4" id="dict1" value="" />
 </div>
</td>

<td>
 <div id="dictionary2">
 <label for="dict2code">dictionary2</label>
 <input type="text" name="dict2code" size="4" id="dict2" value="" />
 </div>
</td>
-->
<td>
 <div id="inputdiv">
  <label for="input">input</label>
  <select name="input" id="input">
   <option value='hk' selected='selected'>KH <!--Kyoto-Harvard--></option>
   <option value='slp1'>SLP1</option>
   <option value='itrans'>ITRANS</option>
   <option value='deva'>Devanagari</option>
   <option value='roman'>IAST</option>
  </select>
 </div>
</td><td>
 <div id="outputdiv">
  <label for="output">output</label>
  <select name="output" id="output">
   <option value='deva'>Devanagari</option>
   <option value='hk'>KH <!--Kyoto-Harvard--></option>
   <option value='slp1'>SLP1</option>
   <option value='itrans'>ITRANS</option>
   <option value='roman' selected='selected'>IAST</option>
  </select>
 </div>
</td><td>
 <div id="accentdiv">  <!-- possibly should be per dictionary -->
  <label for="accent">accent?</label>
 <select name="accent" id="accent">
  <option value="yes">Show</option>
  <option value="no" selected="selected">Hide</option>
 </select>
 </div>
</td></tr>
</table> <!-- preferences -->
 <div id="citationdiv">
  citation1:&nbsp;
  <input type="text" name="key1" size="20" id="key1" value="" style="height:1.4em;"/>

 <input type="text" name="dict1code" size="4" id="dict1" value="" />
 

  <!--<input type="button" id="correction" value="Corrections" /> -->
  <!-- href is set in change function on #dict -->
 <!--
  <a id="correction" href="#" target="Corrections">Corrections</a>
 -->
  <span style="position:absolute;left:650px;">citation2:&nbsp;
  <input type="text" name="key2" size="20" id="key2" value="" style="height:1.4em;"/>

 <input type="text" name="dict2code" size="4" id="dict2" value="" />

  </span>
  <!--<input type="button" id="correction" value="Corrections" /> -->
  <!-- href is set in change function on #dict -->
 <!--
  <a id="correction" href="#" target="Corrections">Corrections</a>
 -->
 </div>
  
 
 <div id="disp">
  <!-- Requesting data will change the src attribute of this iframe -->
  <iframe id="dataframe1">  
   <p>Your browser does not support iframes.</p>
  </iframe>

  <iframe id="dataframe2">  
   <p>Your browser does not support iframes.</p>
  </iframe>
 </div>
</body>
</html>
