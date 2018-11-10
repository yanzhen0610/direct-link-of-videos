import urllib.parse
import urllib.request
import json
import re


def decode_yt_kv(data):
    result = list()
    for row in data.split('&'):
        result.append(row.split('='))
    return result


with urllib.request.urlopen('https://www.youtube.com/watch?v=Et4Y41ZNyao') as yt:
    content = yt.read().decode('utf-8')

if not content:
    print('err')
    exit(1)

for i, line in enumerate(content.split('\n')):
    match = re.search('<\\s*script\\s*>.*ytplayer.config = (\\{.*?\\});.*<\\s*/\\s*script\\s*>', line)
    if match:
        data = match.group(1)
        break
else:
    print('not found var ytplayer')
    exit(1)

data_obj = json.loads(data)
# url_encoded_fmt_stream_map = data_obj['args']['url_encoded_fmt_stream_map']
adaptive_fmts = data_obj['args']['adaptive_fmts']
player_response = data_obj['args']['player_response']
# print(urllib.parse.unquote(url_encoded_fmt_stream_map))
print(adaptive_fmts)
print(player_response)
# print(decode_yt_kv(adaptive_fmts))
# print(decode_yt_kv(player_response))
