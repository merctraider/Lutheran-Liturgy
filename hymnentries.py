import re
import requests
from bs4 import BeautifulSoup
import json

# retrieve the webpage with the links
url = 'https://tacoma.clclutheran.org/songs-and-hymns/'
response = requests.get(url)

# parse the webpage with BeautifulSoup
soup = BeautifulSoup(response.text, 'html.parser')

# find all the links and store them in a dictionary with the hymn number as the key
links = {}
for link in soup.find_all('a'):
    href = link.get('href')
    if href.endswith('.mp3'):
        hymn_num = None
        for sibling in link.previous_siblings:
            if sibling.name is None and sibling.strip():
                match = re.search(r'^(\d+)\s*$', sibling)
                if match:
                    hymn_num = int(match.group(1))
                    break
        if hymn_num is not None:
            links[hymn_num] = href

# open the JSON file and load the data
with open('tlh.json', 'r') as f:
    hymns = json.load(f)

# add the audiofile links to the hymn entries
for hymn in hymns.values():
    match = re.search(r'(\d+)\.', hymn['title'])
    if match:
        hymn_num = int(match.group(1))
        if hymn_num in links:
            hymn['audiofile'] = links[hymn_num]

# write the updated data back to the JSON file
with open('tlh.json', 'w') as f:
    json.dump(hymns, f, indent=4)
