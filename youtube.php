<?php
header( 'Access-Control-Allow-Origin: *' );
header( 'Content-Type: application/json' );

$id = "";
$title = "";
$title_encoded = "";
$result = array();
$error = false;
$error_msg = "";
$video_info = array();

// get input
if ( isset($_REQUEST['url']) && $_REQUEST['url'] != "" )
{
  $url = $_REQUEST['url'];

  // desktop site
  if ( preg_match("/(^https?\:\/\/)?(\w+\.)?youtube\.com\/watch\?(.*&)?v=([A-Za-z0-9_-]+)/", $url, $r) )
  {
    $id = $r[4];
  }
  // mobile
  else if ( preg_match("/(^https?\:\/\/)?youtu\.be\/([A-Za-z0-9_-]+)/", $url, $r) )
  {
    $id = $r[2];
  }
}
// direct enter ID
else if ( isset($_REQUEST['v']) && $_REQUEST['v'] != "" )
{
  $id = $_REQUEST['v'];
}

// get info
if ($id != "")
{
  // load info from 'https://www.youtube.com/get_video_info'
  $info = file_get_contents("http://www.youtube.com/get_video_info?eurl=http%3A%2F%2Fexample.com%2F&sts=17632&video_id=$id");

  // if load info successfully
  if ($info)
  {
    // each field of data was separated by character '&'
    $rows = explode("&", $info);

    foreach($rows as $row)
    {
      // the key and value are separated by character '='
      $col = explode("=", $row);
      $key = $col[0]; $value = $col[1];
      // decoded the value
      $video_info[$key] = urldecode($value);
    }

    // if YouTube return with status fail
    if ($video_info['status'] == "fail")
    {
      $error = true;
      $error_msg = "Video ID not found";
    }
  }
  // if failed to load info from YouTube
  else
  {
    $error = true;
    $error_msg = "Server fault";
  }
}
// if entered invalid parameters
else
{
  $error = true;
  $error_msg = "No URL or video ID";
}

// if there are no errors
if (!$error)
{
  // get the title fo the video
  $title = $video_info['title'];
  $title_encoded = urlencode($title);

  $fmts = explode(',', $video_info['url_encoded_fmt_stream_map']);
  $url_encoded_fmt_stream_map = parse_yt_fmts($fmts);

  $fmts = explode(',', $video_info['adaptive_fmts']);
  $adaptive_fmts = parse_yt_fmts($fmts);

  $result['status'] = "ok";
  $result['id'] = $id;
  $result['title'] = $title;
  $result['url_encoded_fmt_stream_map'] = $url_encoded_fmt_stream_map;
  $result['adaptive_fmts'] = $adaptive_fmts;
}
else
{
  // if there are any errors
  $result['status'] = "fail";
  $result['error_msg'] = $error_msg;
}

// response the result as JSON
echo json_encode($result, JSON_PRETTY_PRINT);

///////////////
// functions //
///////////////

function parse_yt_fmts($str_arr)
{
  $fmts = array();
  foreach($str_arr as $str)
  {
    $rows = explode('&', $str);
    $x = array();
    foreach($rows as $row)
    {
      $col = explode('=', $row);
      $key = $col[0]; $value = urldecode($col[1]);
      $x[$key] = $value;
    }
    if (array_key_exists('url', $x))
    {
      // add the signature
      if (array_key_exists('sig', $x))
        $x['url'] .= "&signature=$x[sig]";
      else if (array_key_exists('signature', $x))
        $x['url'] .= "&signature=$x[signature]";
      else if (array_key_exists('s', $x))
        $x['url'] .= "&signature=" . sig($x['s']);

      // add the encoded title to the url
      global $title_encoded;
      $x['dl_url'] = $x['url'] . "&title=$title_encoded";
    }
    array_push($fmts, $x);
  }
  return $fmts;
}

function str_swap(&$str, $i, $j)
{
  $tmp = $str[$i];
  $str[$i] = $str[$j];
  $str[$j] = $tmp;
}

function sig($sig)
{
  $exchanges = array(
    array(1, 71),
    array(1, 16),
    array(1, 4)
  );
  $slice = array(3, 2);
  foreach($exchanges as $ex)
  {
    str_swap($sig, $ex[0], $ex[1]);
  }
  $sig = substr($sig, $slice[0], strlen($sig) - $slice[0] - $slice[1]);
  $sig = strrev($sig);
  return $sig;
}
