<?php
header( 'Access-Control-Allow-Origin: *' );
header( 'Content-Type: application/json' );

$id = "";
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
    // parse_str( parse_url( $_GET['url'], PHP_URL_QUERY ), $vars );
    // $id = $vars['v'];
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
  $info = file_get_contents("http://www.youtube.com/get_video_info?video_id=$id&el=embedded&ps=default&eurl=&gl=US&hl=en");

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
  $title = $video_info['title'];
  $title_encoded = urlencode($title);

  $streams = explode(',', $video_info['url_encoded_fmt_stream_map']);
  $url_encoded_fmt_stream_map = array();
  foreach ($streams as $stream)
  {
    $rows = explode('&', $stream);
    $x = array();
    foreach ($rows as $row)
    {
      $col = explode('=', $row);
      $key = $col[0]; $value = urldecode($col[1]);
      $x[$key] = $value;
    }
    $x['download_url'] = $x['url']."&title=".$title_encoded;
    array_push($url_encoded_fmt_stream_map, $x);
  }

  $streams = explode(',', $video_info['adaptive_fmts']);
  $adaptive_fmts = array();
  foreach ($streams as $stream)
  {
    $rows = explode('&', $stream);
    $x = array();
    foreach ($rows as $row)
    {
      $col = explode('=', $row);
      $key = $col[0]; $value = urldecode($col[1]);
      $x[$key] = $value;
    }
    array_push($adaptive_fmts, $x);
  }

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
