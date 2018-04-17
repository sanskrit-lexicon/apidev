<?php
/*
 getword_xml_v2.php
  06-01-2017. based on apidev/getword_xml.php
  'key' is treated as a comma-delimited list of keys
  Retrieves info for a given headword; retrieves from web/sqlite/<dict>.xml
  Enhancement:  retrieve multiple headwords
  Enhancement:  retrieve based on normalized spelling
  06-02-2017. In this version, the variants are generated by php, rather
        than being pregenerated by javascript
*/
header("Access-Control-Allow-Origin: *");
header('content-type: application/json; charset=utf-8');
//if (isset($_GET['callback'])) {
//}
$dirpfx = "../../";
require_once($dirpfx . "utilities/transcoder.php"); // initializes transcoder
require_once($dirpfx . "dal.php");  

require_once($dirpfx . "parm.php");

$getParms = new Parm();

$dict = $getParms->dict;
$dal = new Dal($dict);


$keyparm = $getParms->key;  // slp1
$keyparmin = $getParms->keyin;  // original
$ans = array();  // return associative array
$ans['dict']=$dict;
$ans['input']=$getParms->filterin;  //use of filterin is awkward
$ans['output']=$getParms->filter;
$ans['accent']=$getParms->accent;   # yes or no

$keys = explode(',',$keyparm); // slp1
$keysin = explode(',',$keyparmin);  // as per 'input' (e.g., hk)

dev_cmp_js_php($keysin);
$result = [];
for($ikey=0;$ikey<count($keys);$ikey++) {
 $key = $keys[$ikey];
 $keyin = $keysin[$ikey];
 $ans1 = array();
 $ans1['key'] = $key;
 $ans1['keyin'] = $keyin;
 // initialize xml and status for failure
 $ans1['xml'] = array("NOT FOUND");
 // ref: https://en.wikipedia.org/wiki/List_of_HTTP_status_codes
 $ans1['status']=404;  // NOT FOUND use HTTP status codes. 404
 $matches= $dal->get1_xml($key); 
 $nmatches = count($matches);
 if ($nmatches > 0) {
  // reset xml, and status
  # accent-adjustment
  require_once($dirpfx . "accent_adjust.php");
  $dictinfo = $getParms->dictinfo;
  $dictup = $dictinfo->dictupper;
  $accent = $getParms->accent;
  $dictinfo = $getParms->dictinfo;
  $dictup  = $dictinfo->dictupper;
  
  $filter = $getParms->filter;
  $table1 = array();
  for($i=0;$i<count($matches);$i++) {
   $rec = $matches[$i]; //($m['key'],$m['lnum'],$m['data'])
   # it is awkward to have accent_adjust operate on $rec. better on $rec[2]
   $rec1 = accent_adjust($rec,$accent,$dictup);
   $d = $rec1[2]; // data: the xml record for this entry, adjusted for accent
   if (! ($getParms->english)) {
    $d = preg_replace('|<key1>(.*?)</key1>|',"<key1><s>$1</s></key1>",$d);
    $d = preg_replace('|<key2>(.*?)</key2>|',"<key2><s>$1</s></key2>",$d);
    $x = transcoder_processElements($d,"slp1",$filter,"s");
   }else {
    $x = $d;
   }
   $table1[$i]=$x;
  }
  $ans1['xml']=$table1;
  $ans1['status']= 200; // OK
 }
 $result[] = $ans1;
}
$result1 = order_by_wf($result);
$ans['result']=$result1;
$json = json_encode($ans);
if (isset($_GET['callback'])) {
 echo "{$_GET['callback']}($json)";
}else {
 echo $json;
}
exit(0);
function init_word_frequency() {
 $filein = "../v0.1/word_frequency.txt";
 $lines = file($filein,FILE_IGNORE_NEW_LINES);
 $ans = array();
 foreach($lines as $line) {
  if (preg_match('|([^ ]*) *([0-9]*)$|',$line,$matches)) {
   $key = $matches[1];
   $val = $matches[2];
   $ival = (int)$val;
   $ans[$key] = $ival;
  }
 }
 return $ans;
}
function wf_cmp($a,$b) {
 // $a, $b are objects: $a = $result[$i]
 if ($a['wf'] == $b['wf']) {
  return 0;
 }
 return  ($a['wf'] > $b['wf']) ? -1 : 1;
}
function order_by_wf($result) {
 $wfreqs = init_word_frequency();
 $result1 = array();
 foreach($result as $ans1) {
  $key = $ans1['key'];  // slp1, consistent with coding of word_frequency
  if (isset($wfreqs[$key])) {
   $wf = $wfreqs[$key];
  }else if ($ans1['status'] == 200) {
   $wf = -1;
  }else{
   $wf = -9;
  }
  $ans1['wf']=$wf;
  $result1[] = $ans1;
 }
 usort($result1,"wf_cmp");
 return $result1;
}
function dev_cmp_js_php($keysinjs) {
 require_once('simple_search.php');
 require_once('../../dbgprint.php');
 $word = $keysinjs[0];
 $word_slp1 = $word;  // temporary
 $ssobj = new Simple_Search($word_slp1,$word);
 $keys = $ssobj->keys;
 $keysin = $ssobj->keysin;
 $dbg=true;
 $njs = count($keysinjs);
 $nss = count($keysin);
 dbgprint($dbg,"$njs keys from js\n");
 dbgprint($dbg,"$nss keys from php\n");
 $nprob=0;
 if ($nss == $njs) {
  for($i=0;$i<$nss;$i++) {
   if ($keysinjs[$i] != $keysin[$i]) {
    $nprob = $nprob + 1;
    dbgprint($dbg,"Problem @ $i: {$keysinjs[$i]} != {$keysin[$i]}\n");
   }
  }
  if ($nprob == 0) {
   dbgprint($dbg,"keysin is same as keysinjs\n");
  }
 } else {
   if ($nss < $njs) {
    $n = $njs;
   }else {
    $n = $nss;
   }
   for($i=0;$i<$n;$i++) {
    $keyjs = "NONE";
    $keyss = "NONE";
    if ($i < $nss) {$keyss = $keysin[$i];}
    if ($i < $njs) {$keyjs = $keysinjs[$i];}
    if ($keyjs == $keyss) {
     $code = "OK";
    }else {
     $code = "PROB";
    }
    dbgprint($dbg,"$i : $keyjs  $keyss  $code\n");
   }
  }

}

?>
