<?php
/*
 getword_list_0.1_main.php  
 Variant of getword_list_1.0.php of fetching_v0.3b.
 Designed to work with list-0.2s.html
  06-01-2017. based on apidev/getword_xml.php
  Retrieves info for a given headword; retrieves from web/sqlite/<dict>.xml
  Enhancement:  retrieve multiple headwords
  Enhancement:  retrieve based on normalized spelling
  06-02-2017. In this version, the variants are generated by php, rather
        than being pregenerated by javascript
  06-05-2017. Compute variants with SLP
  08-11-2017. getword_list_processone() function does all the work,
        using values from php $_REQUEST global. Generates no stdout output
  08-17-2017. word_frequency now from ../wf0/wf.txt -- 
*/
require_once('get_parent_dirpfx.php');
function getword_list_processone() {
$ru0 = microtime(true);
// $dirpfx is assumed to be the 'csl-apidev' directory.
// We make use of several functions in csl-apidev.
$dirpfx = get_parent_dirpfx("simple-search");
//$dbg = true;
require_once($dirpfx . "utilities/transcoder.php"); // initializes transcoder
require_once($dirpfx . "dal.php");  
require_once($dirpfx . 'dbgprint.php');
require_once('simple_search.php');
// class to access the hwnorm1 database
require_once('dalnorm.php');

// access parameters from $_REQUEST
require_once($dirpfx . "parm.php");
$getParms = new Parm();
dbgprint($dbg,"Entering getword_list_1.0.php 4\n");

// Part of the simple search algorithm depends on the 
// dictionary
$dict = $getParms->dict;
dbgprint($dbg,"getword_list_1.0.php: dict=$dict\n");
$dal = new Dal($dict);
// WARNING: the relative path to sanhw1 is sensitive to location of
//   this file.
#$dalnorm = new Dalnorm('hwnorm1c','../hwnorm1');
$dirpfx = get_parent_dirpfx("simple-search");
$hwnorm1 = $dirpfx . "simple-search/hwnorm1";
$dalnorm = new Dalnorm('hwnorm1c',$hwnorm1);
// Ordering of results depends on a word frequency file.
$wfreqs = init_word_frequency();

// keyparmin is the key input. It is what the user requested.
// Assumed to be in utf-8 encoding
$keyparmin = $getParms->keyin;  // original
dbgprint($dbg,"keyparmin=$keyparmin\n");
// php function. Convert back to utf-8
// This is done already in javascript list-0.2s_(xampp)_rw.php

$ru1 = microtime(true); //getrusage();
$utime = $ru1 - $ru0;
dbgprint($dbg,"time before simple_search: $utime s\n");

$ssobj = new Simple_Search($keyparmin,$dict);

$ru2 = microtime(true);
$utime = $ru2 - $ru1;
dbgprint($dbg,"time used simple_search: $utime s\n");

$keysin = $ssobj->normkeys;  // normalized slp1 spelling
// 11-01-2017. user keyin, slp1, norm. So we can identify
// whether the user's spelling is one of the results.
$keyparmin_slp1 = $ssobj->user_keyin;
$keyparmin_norm = $ssobj->user_keyin_norm;  
$ans = array();  // return associative array
$ans['dict']=$dict;
$ans['input']=$getParms->filterin;  //should be 'simple'  Not of interest
$ans['output']=$getParms->filter;   // The output type for Sanskrit
$ans['accent']=$getParms->accent;   // # yes or no  Not used otherwise

/* In the next step, we generate an array $result of objects 
   ($ans1 is the variable name of this object).
   We use the $ans fields dict, input, output, accent
  - Generally, there is one result object for each $key in $keysin.
  - Rather confusing - perhaps there can be no object for some
  - values of $key  (if $key does not occur in dictionary $dict).
*/
$nkeysin = count($keysin);

dbgprint($dbg,"getword_list_1.0_main back from simple_search\n");
dbgprint($dbg,"$keyparmin has $nkeysin alternates\n");
$result = [];  
for($ikey=0;$ikey<count($keysin);$ikey++) {
 $key = $keysin[$ikey];
 $ans1 = array();
 $ans1['key'] = $key;  // This is a normalized spelling.
 $ans1['keyin'] = 'NF';
 // initialize xml and status for failure
 $ans1['xml'] = array("NOT FOUND");
 // ref: https://en.wikipedia.org/wiki/List_of_HTTP_status_codes
 $ans1['status']=404;  // NOT FOUND use HTTP status codes. 404
 // Assume key already normalized
 $matches = $dalnorm->get1($key);
 $nmatches = count($matches);
 $dictheadwords = [];
 #dbgprint($dbg,"for key=$key, nmatches1=$nmatches\n");
 if ($nmatches > 0) {
  // We know that $key is the normalized spelling of a
  //  headword in SOME dictionary
  // For this display, we further want to require that it is a headword
  // in the user-selected dictionary ($dict)
  // In some rare cases (example: norm = vfti), dict=mw, there may be more
  // than 1 headword spelling (key1) (example: key1=vfti, vftti).
  # $nmatches is either 0 or 1 for this hwnorm1c database
  $i = 0;
   
   $rec = $matches[$i]; //($m['key'],$m['data'])
   $parts = explode(';',$rec[1]);
   $dictup = strtoupper($dict);  // dictlist below are upper
   #dbgprint($dbg,"dictup=$dictup\n");
   foreach($parts as $part) {
    list($dictheadword0,$dictliststring) = explode(':',$part);
    dbgprint($dbg,"$dictheadword0  IS IN $dictliststring\n");
    $dictlist = explode(',',$dictliststring);
    if (in_array($dictup,$dictlist)) {
      $dictheadwords[]=$dictheadword0; // It is slp1 spelling
      dbgprint($dbg,"found $dictheadword0 in $dictup\n");
      // break;  # DON'T break  (think of vfti example above).
    }
   }

 }
  dbgprint(true,"getword_list_1.0_main: input = {$ans['input']}, output = {$ans['output']}\n");

 foreach($dictheadwords as $dictheadword) {
  // This loop doesn't execute unless $nmatches>0.
  // transcode to HK for this display
  $ans1['keyin']  =  transcoder_processString($dictheadword,"slp1",$ans['input']);
  // reset xml, and status
  // 11-01-2017. Extra flag to indicate whether this is the user's input word
  $ans1['user_key_flag'] = ($dictheadword == $keyparmin_slp1);
  # accent-adjustment
  #require_once($dirpfx . "accent_adjust.php");
  $dictinfo = $getParms->dictinfo;
  $dictup = $dictinfo->dictupper;
  $accent = $getParms->accent;
  
  $filter = $getParms->filter;
  $ans1['dicthw']=$dictheadword;  // slp1 spelling of $key in OUR dict
  $ans1['dicthwoutput']=transcoder_processString($dictheadword,"slp1",$ans['output']);
  $i = 0;
  #dbgprint(true,"$i\n");
  $rec = $matches[$i]; //($m['key'],$m['data'])
  // For this work simple_search, no need to do this adjustment
  // $rec[1] is the data 
  $ans1['xml']=$rec[1];
  $ans1['status']= 200; // OK
  
  $result[] = $ans1;
 }
}

$result1a = order_by_wf($result,$wfreqs);
$result1 = put_user_word_first($result1a);
$ans['result']=$result1;

$ru3 = microtime(true); //getrusage();
$utime = $ru3 - $ru2;
dbgprint($dbg,"time used gathering data: $utime s\n");

return $ans;
}  // end of getword_list_processone

function init_word_frequency() {
 # word_frequency_adj.txt removes duplicates from word_frequency.txt
 # see readme.org in ../v0.1
 #$filein = "../v0.1/word_frequency_adj.txt";
 #$filein = "../wf0/wf.txt";  // 08-17-2017
 $dirpfx = get_parent_dirpfx("simple-search");
 $filein = $dirpfx . "simple-search/wf0/wf.txt";
 $lines = file($filein,FILE_IGNORE_NEW_LINES);
 $ans = array();
 $nans = 0;
 $dbg=false;
 foreach($lines as $line) {
  $line = trim($line);
  if (preg_match('|([^ ]*) *([0-9]*)$|',$line,$matches)) {
   $key = $matches[1];
   $val = $matches[2];
   $ival = intval($val);
   $ans[$key] = $ival;
   $nans = $nans + 1;
   if ($nans <= 10) {
    dbgprint($dbg,"init_word_frequency: $nans , '$key', $val, $ival\n");
   }
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
 #return  ($a['wf'] > $b['wf']) ? 1 : -1;
}
function put_user_word_first($result) {
 $iuser = -1;
 $i = 0;
 foreach($result as $ans1) {
  if ($ans1['user_key_flag']) {
   $iuser = $i;
   break;
  }
  $i++;
 }
 if ($iuser == -1) {
  return $result;
 }
 # put user index first.  Otherwise don't change ordering
 $result1 = array(); 
 for($i=0;$i<count($result);$i++) {
  if ($i == 0) {
   $result1[] = $result[$iuser];
  }else if ($i <= $iuser) {
   $result1[] = $result[$i-1];
  }else { # $i > $iuser
   $result1[] = $result[$i];
  }
 }
 return $result1;
}
function order_by_wf($result,$wfreqs) {
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
 $dbg=false;
 if ($dbg) {
  dbgprint(true,"wfreqs at daRqa = " . $wfreqs['daRqa'] . "\n");
  foreach($result1 as $ans1) {
   dbgprint(true,$ans1['key'] . "  " . $ans1['wf'] . "\n");
  }
 }
 return $result1;
}

?>
