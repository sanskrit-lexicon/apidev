<?php
/* servepdf.php  Apr 27, 2015 Multidictionary display of scanned images
  Similar to servepdf for the dictionaries
Parameters:
 dict: one of the dictionary codes (case insensitive)
 page: a specific page of the dictionary.  In the form of the contents
       of a <pc> element
 key: a headword, in SLP1.  
  Only one of 'page' and 'key' should be used.  If both are present, then
  'key' parameter is ignored and 'page' parameter prevails.
*/
$dict = $_GET['dict'];
$page = $_GET['page'];
$key =  $_GET['key']; // optional
$dbg=False;
if ($dbg &&(!$dict)) {
 $dict = $argv[1];
 $key = $argv[2];
}
require_once('dictinfo.php');
$dictinfo = new DictInfo($dict);
$year = $dictinfo->get_year();
$webpath = $dictinfo->get_webPath();
$dictupper = $dictinfo->dictupper;
$dbg=true;
if ((!$page)&&$key) {// Try to get $page from 'key' parm
 require_once('dal.php');
 $dal = new Dal($dict);
 $recs = $dal->get1($key); // Assume $key is in SLP1
 if ($dbg) {
  echo "<br/><br/><br/><br/>" . count($recs). "  records for $key<br/>\n";
  echo "<br>page=$page<br/>";
 }
 if (count($recs) > 0) { // get <pc> element from first rec
  $rec = $recs[0];
  $data = $rec[2];  # $rec['data'];
  print "first record data = \n$data";
  if (preg_match('|<tail>.*?<pc>(.*?)</pc>|',$data,$matches)) {
   $page = $matches[1];
  }
  if ($dbg) {
   echo "<br/><br/><br/><br/>" . count($recs). "  records for $key<br/>\n";
   echo "<br>page=$page<br/>";
  }
 }
 // In any case, close this database connection
 $dal->close();
}

list($filename,$pageprev,$pagenext)=getfiles($webpath,$page,$dictupper);
$dir = "$webpath/pdfpages"; // location of pdf files
$pdf = "$dir/$filename";
if ($dbg) { 
 echo "dict=$dict, year=$year<br/>";
 echo "webpath={$webpath}<br/>";
 echo "scanpath =".DictInfo::$scanpath."<br/>";
 echo "page=$page, $filename,$pageprev,$pagenext<br/>";
}

?>
<html>
<head>
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=utf-8">
<title><?= $dictupper?> Cologne Scan</title>
<link rel='stylesheet' type='text/css' href='//www.sanskrit-lexicon.uni-koeln.de/scans/awork/apidev/css/serveimg.css' />
</head>
<body>
<?php  
$imageParms = array(
 'WIL' => "width ='1000' height='1500'",
 'PW'  => "width ='1600' height='2300'",
 'CCS' => "width ='1400' height='2000'",
 'MD'  => "width ='1000' height='1370'",
);
$imageParm = $imageParms[$dictinfo->dictupper];
?>
<?php if ($imageParm){?>
<img src='<?=$pdf?>' <?=$imageParm?> />
<?php }else{?>
<object id='servepdf' type='application/pdf' data='<?=$pdf?>'
  style="width: 98%; height:98%"></object>
<?php }?>

<div id='pagenav'>
<a href="servepdf.php?dict=<?=$dict?>&page=<?=$pageprev?>" 
   class='nppage'><span class='nppage1'>&lt;</span>&nbsp;</a>
<a href="servepdf.php?dict=<?=$dict?>&page=<?=$pagenext?>" 
   class='nppage'><span class='nppage1'>&gt;</span>&nbsp;</a>
</div>
</body>
</html>
<?php
function getfiles($webpath,$pagestr_in0,$dictupper) { 
 // Next line for MW, where pagestr_in0 may start with 'Page', which we remove
 $pagestr_in0 = preg_replace('|^[^0-9]+|','',$pagestr_in0);
 // Recognize two basic cases: vol-page or page.
 // The pdffiles cases are usually one of the two
 // For these, we remove characters (such as column designations) 
 // that may be present if pagestr_in0 comes from the <pc> elt of the dictionary
 // as when the 'key' input GET parameter.
 if (preg_match('|^([1-9]-[0-9]+)|',$pagestr_in0,$matches)) {
  $pagestr_in = $matches[1];
 }elseif (preg_match('|^([0-9]+)|',$pagestr_in0,$matches)) {
  $pagestr_in = $matches[1];
 }else {
  // not sure if this case ever obtains
  $pagestr_in = $pagestr_in0;
 }

 $pagestr_in = preg_replace('/^0+/','',$pagestr_in);
 // echo "{$pagestr_in0}  -> {$pagestr_in}\n";
 //exit(1);
 $dir = "$webpath/webtc";
 $filename="$dir/pdffiles.txt";
 $lines = file($filename);
 $pagearr=array(); //sequential
 $pagehash=array(); // hash
 $n=0;
 foreach($lines as $line) {
  list($pagestr,$pagefile,$pagetitle) = preg_split('|:|',$line);
  # pagetitle currently unused
  $n++;
  //$pagehash[$pagestr]=$n;
  $pagestr_trim = preg_replace('/^0+/','',$pagestr);
  $pagehash[$pagestr_trim]=$n;
  $pagearr[$n]=array($pagestr,$pagefile);
 }
 $ncur = $pagehash[$pagestr_in];
 if (!$ncur) {
  $pagenum = intval($pagestr_in); // result is 0 if not a string of digits
  if (($pagenum % 2) == 1) {
   $pagenum = $pagenum - 1;
  }
  $pagestr = "$pagenum";
  $ncur = $pagehash[$pagestr];
 }
 if ((!$ncur) && ($dictupper == 'PWG')) {
  $lnum = $pagestr_in;
  list($vol,$page) =  preg_split('/[,-]/',$lnum);
  $pagestr=$lnum;
  $ipage = intval($page);
  if (($ipage % 2) == 0) {
   $ipage = $ipage - 1;
   $pagestr = sprintf('%s-%04d',$vol,$ipage);
   $ncur = $pagehash[$pagestr]; 
  }
 }
 if ((!$ncur) && ($dictupper == 'GRA')) {
  $page= $pagestr_in;
  $pagestr=$page;
  $ipage = intval($page);
  if (($ipage % 2) == 0) {
   $ipage = $ipage - 1;
   $pagestr = sprintf('%d',$ipage);
   $ncur = $pagehash[$pagestr]; 
  }
  //echo "check GRA: $pagestr_in, $ipage, $ncur<br/>\n";
  //exit(1);
 }
 if(!$ncur) {
  $ncur=1;
 }
 list($pagestrcur,$filecur) = $pagearr[$ncur];
 $nnext = $ncur + 1;
 if ($nnext > $n) {$nnext = 1;}
 $nprev = $ncur - 1;
 if ($nprev < 1) {$nprev = $n;}
 //echo "nprev,ncur,nnext = $nprev,$ncur,$nnext\n";
 list($pagenext,$dummy) = $pagearr[$nnext];
 list($pageprev,$dummy) = $pagearr[$nprev];
 //echo "($filecur,$pageprev,$pagenext)";
 //exit(1);
 return array($filecur,$pageprev,$pagenext);
}

?>
