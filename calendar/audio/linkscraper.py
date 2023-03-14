import requests
from bs4 import BeautifulSoup

url = 'https://tacoma.clclutheran.org/songs-and-hymns/'
response = requests.get(url)
soup = BeautifulSoup(response.text, 'html.parser')

links = []
for link in soup.find_all('a'):
    href = link.get('href')
    if href:
        links.append(href)

link_counts = {}
for link in links:
    if link in link_counts:
        link_counts[link] += 1
    else:
        link_counts[link] = 1

duplicate_links = []
for link, count in link_counts.items():
    if count > 1:
        duplicate_links.append(link)

print(duplicate_links)
