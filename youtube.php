<?php
header( 'Access-Control-Allow-Origin: *' );
header( 'Content-Type: application/json' );

// input var
$id = "";

// info
$video_info = false;

// results
$result = array();
$error = false;

// get input
if (isset($_REQUEST['url']) && $_REQUEST['url'] != '')
{
  $url = $_REQUEST['url'];

  // desktop site
  if (preg_match("/(https?\:\/\/)?(\w+\.)?youtube\.com\/watch\?(.*&)?v=([A-Za-z0-9_-]+)/", $url, $r))
  {
    $id = $r[4];
  }
  // mobile
  else if (preg_match("/(https?\:\/\/)?youtu\.be\/([A-Za-z0-9_-]+)/", $url, $r))
  {
    $id = $r[2];
  }
}
// direct enter ID
else if (isset($_REQUEST['v']) && $_REQUEST['v'] != '')
{
  $id = $_REQUEST['v'];
}

// get info
if (!$error && $id != "")
{
  $info = false;

  // re-try on failure
  for ($i = 0; $i < 2; ++$i)
  {
    $info = file_get_contents("https://www.youtube.com/watch?v=$id");
    if ($info) break;
    sleep(1);
  }

  // if load info successfully
  if ($info)
  {
    if (preg_match('/<\s*script\s*>.*ytplayer\.config = (\{.*?\});.*<\s*\/\s*script\s*>/', $info, $matches))
    {
      $video_info = json_decode($matches[1], true);
    }
    else
    {
      $error = "Can't get video info where video ID=$id";
    }
  }
  // if failed to load info from YouTube
  else
  {
    $error = "Server fault: cannot fetch content from https://www.youtube.com/watch?v=$id";
  }
}
// if entered invalid parameters
else
{
  $error = "No URL or video ID";
}

// if there are no errors
if (!$error)
{
  $player_response = json_decode(
  $video_info['args']['player_response'], true);
  $video_details = $player_response['videoDetails'];
  $streaming_data = $player_response['streamingData'];

  $result['status'] = 'ok';
  $result['videoId'] = $id;
  $result['channelId'] = $video_details['channelId'];
  $result['author'] = $video_details['author'];
  $result['title'] = $video_info['args']['title'];
  $result['viewCount'] = $video_details['viewCount'];
  $result['keywords'] = $video_details['keywords'];
  $result['lengthSeconds'] = $video_details['lengthSeconds'];
  $result['thumbnail'] = $video_details['thumbnail'];
  $result['streamingData'] = $streaming_data;
  unset($result['streamingData']['probeUrl']);
}
else
{
  // if there are any errors
  $result['status'] = 'fail';
  $result['reason'] = $error;
}

// response the result as JSON
$output = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$content_length = strlen($output);
header( "Content-Length: $content_length" );
echo $output;
