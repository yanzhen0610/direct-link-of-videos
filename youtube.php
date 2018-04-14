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
  if ( preg_match("/(https?\:\/\/)?(\w+\.)?youtube\.com\/watch\?(.*&)?v=([A-Za-z0-9_-]+)/", $url, $r) )
  {
    $id = $r[4];
  }
  // mobile
  else if ( preg_match("/(https?\:\/\/)?youtu\.be\/([A-Za-z0-9_-]+)/", $url, $r) )
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
  $info = file_get_contents("http://www.youtube.com/get_video_info?eurl=http%3A%2F%2Fexample.com%2F&video_id=$id&gl=US&hl=en");

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
      $error_msg = $video_info['reason'];
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

// parse to array
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
      $x['content-length'] = get_url_content_length($x['url']);
    }
    array_push($fmts, $x);
  }
  return $fmts;
}

// swap function
function str_swap(&$str, $i, $j)
{
  $tmp = $str[$i];
  $str[$i] = $str[$j];
  $str[$j] = $tmp;
}

// get the real signature
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

// get the content length of an URL from response header
function get_url_content_length($url)
{
  $len = false;

  if (preg_match("/^((https?)\:\/\/)?(([\w-]*\.)+([\w-]+\.?))(\/.*)?$/", $url, $matches))
  {
    $scheme = $matches[2];
    $hostname = $matches[3];
    $query = $matches[6];

    $port = getservbyname($scheme, 'tcp');
    if ($port === false) return -1;
    $address = gethostbyname($hostname);
    if ($address === $hostname) return -1;

    // request header
    $request_str = "GET $query HTTP/1.1\r\n";
    $request_str .= "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n";
    $request_str .= "Accept-Encoding: gzip, deflate, br\r\n";
    $request_str .= "Accept-Language: en-US,en;q=0.5\r\n";
    $request_str .= "Cache-Control: no-cache\r\n";
    $request_str .= "Connection: keep-alive\r\n";
    $request_str .= "DNT: 1\r\n";
    $request_str .= "Host: $hostname\r\n";
    $request_str .= "Pragma: no-cache\r\n";
    $request_str .= "Upgrade-Insecure-Requests: 1\r\n";
    $request_str .= "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:59.0) Gecko/20100101 Firefox/59.0\r\n";
    $request_str .= "\r\n";

    $response_header = "";

    // 0.1 sec timeout
    if ($socket = stream_socket_client('tlsv1.2://'.$hostname.':'.$port, $errno, $errstr, 0.1))
    {
      fwrite($socket, $request_str);
      $data = "";
      while (!feof($socket))
      {
        $data .= fread($socket, 1024);
        if (preg_match("/(\r\n\r\n)/", $data, $matches, PREG_OFFSET_CAPTURE))
        {
          $response_header = substr($data, 0, $matches[1][1] + 1);
          break;
        }
      }
      fclose($socket);
    }

    if (preg_match("/[cC]ontent-[lL]ength\:\s*(\d+)/", $response_header, $matches))
    {
      if (is_numeric($matches[1]))
      {
        $len = (int) $matches[1];
      }
    }
  }

  return $len;
}
