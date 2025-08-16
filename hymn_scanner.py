import json
import re
import requests
from bs4 import BeautifulSoup
import time
from urllib.parse import urljoin

def scan_hymns_for_missing(json_file_path):
    """
    Scans a JSON file containing hymns to identify missing hymn numbers from 1 to 668.
    
    Args:
        json_file_path (str): Path to the JSON file containing hymn data
    
    Returns:
        tuple: (present_hymns, missing_hymns, data) - sets of hymn numbers and loaded data
    """
    try:
        # Read the JSON file
        with open(json_file_path, 'r', encoding='utf-8') as file:
            data = json.load(file)
        
        # Extract hymn numbers from keys
        present_hymns = set()
        
        # Look for keys that match the pattern "hymn" followed by numbers
        hymn_pattern = re.compile(r'^hymn(\d+)$')
        
        for key in data.keys():
            match = hymn_pattern.match(key)
            if match:
                hymn_number = int(match.group(1))
                # Only consider hymns in the range 1-668
                if 1 <= hymn_number <= 668:
                    present_hymns.add(hymn_number)
        
        # Generate complete set of expected hymn numbers
        expected_hymns = set(range(1, 669))  # 1 to 668 inclusive
        
        # Find missing hymns
        missing_hymns = expected_hymns - present_hymns
        
        return present_hymns, missing_hymns, data
        
    except FileNotFoundError:
        print(f"Error: File '{json_file_path}' not found.")
        return set(), set(), {}
    except json.JSONDecodeError:
        print(f"Error: Invalid JSON format in file '{json_file_path}'.")
        return set(), set(), {}
    except Exception as e:
        print(f"Error reading file: {e}")
        return set(), set(), {}

def scrape_hymn_from_url(hymn_number):
    """
    Scrapes a hymn from the TLH website.
    
    Args:
        hymn_number (int): The hymn number to scrape
    
    Returns:
        dict: Hymn data in the required format, or None if not found
    """
    url = f"https://clcgracelutheranchurch.org/fridley/hymns/tlh/tlh{hymn_number}.htm"
    
    try:
        response = requests.get(url, timeout=10)
        response.raise_for_status()
        
        soup = BeautifulSoup(response.content, 'html.parser')
        
        # Check if it's a 404 error page
        body = soup.find('body')
        if body and 'error404' in body.get('class', []):
            print(f"  Hymn {hymn_number}: Not found (404)")
            return None
        
        # Extract title
        title_tag = soup.find('title')
        if not title_tag:
            print(f"  Hymn {hymn_number}: No title found")
            return None
        
        title_text = title_tag.get_text().strip()
        
        # Extract just the hymn title part (remove "TLH {number}: " prefix)
        title_match = re.match(r'TLH \d+:\s*(.+)', title_text)
        if title_match:
            hymn_title = title_match.group(1).strip()
        else:
            hymn_title = title_text
        
        # Extract lyrics from the paragraph
        lyrics_paragraph = soup.find('p')
        if not lyrics_paragraph:
            print(f"  Hymn {hymn_number}: No lyrics paragraph found")
            return None
        
        # Get the HTML content and split by <br><br><br> (verse separators)
        lyrics_html = str(lyrics_paragraph)
        
        # Remove the <p> tags
        lyrics_html = re.sub(r'^<p[^>]*>', '', lyrics_html)
        lyrics_html = re.sub(r'</p>$', '', lyrics_html)
        
        # Split verses by triple line breaks
        verses = re.split(r'<br\s*/?>\s*<br\s*/?>\s*<br\s*/?>', lyrics_html)
        
        # Clean up each verse
        cleaned_verses = []
        for i, verse in enumerate(verses):
            if verse.strip():
                # Replace <br> tags with \r\n
                verse_text = re.sub(r'<br\s*/?>', '\r\n', verse)
                # Remove any remaining HTML tags
                verse_text = re.sub(r'<[^>]+>', '', verse_text)
                # Clean up whitespace
                verse_text = verse_text.strip()
                
                if verse_text:
                    # Add verse number if not present
                    if not re.match(r'^\d+\.?\s', verse_text):
                        verse_text = f"{i+1} {verse_text}"
                    
                    cleaned_verses.append(verse_text)
        
        if not cleaned_verses:
            print(f"  Hymn {hymn_number}: No verses found")
            return None
        
        # Create the hymn entry
        hymn_data = {
            "title": f"{hymn_number}. {hymn_title}",
            "lyrics": cleaned_verses,
            "audiofile": f"http://lutherantacoma.com/hymns/{hymn_number:03d}.mp3"
        }
        
        print(f"  Hymn {hymn_number}: Successfully scraped - {hymn_title}")
        return hymn_data
        
    except requests.RequestException as e:
        print(f"  Hymn {hymn_number}: Network error - {e}")
        return None
    except Exception as e:
        print(f"  Hymn {hymn_number}: Error - {e}")
        return None

def scrape_missing_hymns(missing_hymns, delay=1):
    """
    Scrapes all missing hymns from the website.
    
    Args:
        missing_hymns (set): Set of missing hymn numbers
        delay (float): Delay between requests in seconds
    
    Returns:
        dict: Dictionary of successfully scraped hymns
    """
    scraped_hymns = {}
    total_missing = len(missing_hymns)
    
    print(f"\nStarting to scrape {total_missing} missing hymns...")
    print("=" * 50)
    
    for i, hymn_number in enumerate(sorted(missing_hymns), 1):
        print(f"Scraping {i}/{total_missing}: Hymn {hymn_number}")
        
        hymn_data = scrape_hymn_from_url(hymn_number)
        
        if hymn_data:
            scraped_hymns[f"hymn{hymn_number}"] = hymn_data
        
        # Add delay to be respectful to the server
        if i < total_missing:  # Don't delay after the last request
            time.sleep(delay)
    
    print("=" * 50)
    print(f"Scraping complete! Successfully scraped {len(scraped_hymns)} hymns.")
    
    return scraped_hymns

def update_json_file(json_file_path, original_data, scraped_hymns):
    """
    Updates the JSON file with scraped hymn data.
    
    Args:
        json_file_path (str): Path to the JSON file
        original_data (dict): Original JSON data
        scraped_hymns (dict): Newly scraped hymn data
    """
    # Merge the data
    updated_data = original_data.copy()
    updated_data.update(scraped_hymns)
    
    # Create backup of original file
    backup_path = json_file_path + '.backup'
    try:
        with open(json_file_path, 'r', encoding='utf-8') as original:
            with open(backup_path, 'w', encoding='utf-8') as backup:
                backup.write(original.read())
        print(f"Backup created: {backup_path}")
    except Exception as e:
        print(f"Warning: Could not create backup - {e}")
    
    # Write updated data
    try:
        with open(json_file_path, 'w', encoding='utf-8') as file:
            json.dump(updated_data, file, indent=2, ensure_ascii=False)
        print(f"Updated JSON file: {json_file_path}")
        print(f"Added {len(scraped_hymns)} new hymns to the collection.")
    except Exception as e:
        print(f"Error updating JSON file: {e}")

def print_hymn_report(present_hymns, missing_hymns):
    """
    Prints a detailed report of present and missing hymns.
    
    Args:
        present_hymns (set): Set of present hymn numbers
        missing_hymns (set): Set of missing hymn numbers
    """
    total_expected = 668
    total_present = len(present_hymns)
    total_missing = len(missing_hymns)
    
    print("=" * 60)
    print("HYMN COLLECTION ANALYSIS REPORT")
    print("=" * 60)
    print(f"Total Expected Hymns: {total_expected}")
    print(f"Total Present Hymns:  {total_present}")
    print(f"Total Missing Hymns:  {total_missing}")
    print(f"Completion Rate:      {(total_present/total_expected)*100:.1f}%")
    print("=" * 60)
    
    if missing_hymns:
        print("\nMISSING HYMNS:")
        print("-" * 40)
        
        # Convert to sorted list for better readability
        missing_list = sorted(missing_hymns)
        
        # Print first 20 missing hymns for reference
        display_count = min(20, len(missing_list))
        for i in range(0, display_count, 10):
            line_hymns = [str(num) for num in missing_list[i:i+10]]
            print(", ".join(line_hymns))
        
        if len(missing_list) > 20:
            print(f"... and {len(missing_list) - 20} more")
    
    else:
        print("\n🎉 ALL HYMNS ARE PRESENT! Complete collection detected.")
    
    print("\n" + "=" * 60)

def main():
    print("TLH Hymn Collection Scanner and Scraper")
    print("=" * 45)
    
    # Get JSON file path
    json_file_path = input("Enter the path to your hymns JSON file: ").strip()
    
    if not json_file_path:
        json_file_path = "hymns.json"  # Default filename
        print(f"Using default filename: {json_file_path}")
    
    print(f"\nScanning hymns in: {json_file_path}")
    print("Looking for hymns numbered 1 through 668...")
    
    # Scan for missing hymns
    present_hymns, missing_hymns, original_data = scan_hymns_for_missing(json_file_path)
    
    if not present_hymns and not missing_hymns:
        print("Could not read the JSON file. Exiting.")
        return
    
    # Print report
    print_hymn_report(present_hymns, missing_hymns)
    
    if missing_hymns:
        # Ask if user wants to scrape missing hymns
        scrape_option = input(f"\nScrape {len(missing_hymns)} missing hymns from the website? (y/n): ").lower()
        
        if scrape_option == 'y':
            # Ask for delay between requests
            try:
                delay = float(input("Enter delay between requests in seconds (default 1): ") or "1")
            except ValueError:
                delay = 1
                print("Using default delay of 1 second")
            
            # Scrape missing hymns
            scraped_hymns = scrape_missing_hymns(missing_hymns, delay)
            
            if scraped_hymns:
                # Ask if user wants to update the JSON file
                update_option = input(f"\nUpdate JSON file with {len(scraped_hymns)} scraped hymns? (y/n): ").lower()
                
                if update_option == 'y':
                    update_json_file(json_file_path, original_data, scraped_hymns)
                    
                    # Show final report
                    print("\n" + "=" * 60)
                    print("FINAL REPORT")
                    print("=" * 60)
                    new_total = len(present_hymns) + len(scraped_hymns)
                    new_missing = 668 - new_total
                    print(f"Total Hymns Now:      {new_total}")
                    print(f"Still Missing:        {new_missing}")
                    print(f"New Completion Rate:  {(new_total/668)*100:.1f}%")
                else:
                    print("JSON file not updated.")
            else:
                print("No hymns were successfully scraped.")
        else:
            print("Scraping cancelled.")
    
    print("\nOperation complete!")

if __name__ == "__main__":
    main()