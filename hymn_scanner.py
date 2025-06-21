import json
import re

def scan_hymns_for_missing(json_file_path):
    """
    Scans a JSON file containing hymns to identify missing hymn numbers from 1 to 668.
    
    Args:
        json_file_path (str): Path to the JSON file containing hymn data
    
    Returns:
        tuple: (present_hymns, missing_hymns) - sets of hymn numbers
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
        
        return present_hymns, missing_hymns
        
    except FileNotFoundError:
        print(f"Error: File '{json_file_path}' not found.")
        return set(), set()
    except json.JSONDecodeError:
        print(f"Error: Invalid JSON format in file '{json_file_path}'.")
        return set(), set()
    except Exception as e:
        print(f"Error reading file: {e}")
        return set(), set()

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
        
        # Group consecutive missing hymns for compact display
        ranges = []
        start = missing_list[0]
        end = start
        
        for i in range(1, len(missing_list)):
            if missing_list[i] == end + 1:
                end = missing_list[i]
            else:
                if start == end:
                    ranges.append(str(start))
                else:
                    ranges.append(f"{start}-{end}")
                start = missing_list[i]
                end = start
        
        # Add the last range
        if start == end:
            ranges.append(str(start))
        else:
            ranges.append(f"{start}-{end}")
        
        # Print ranges, 10 per line
        for i in range(0, len(ranges), 10):
            print(", ".join(ranges[i:i+10]))
        
        print(f"\nMissing hymns listed individually:")
        for i in range(0, len(missing_list), 20):
            line_hymns = [str(num) for num in missing_list[i:i+20]]
            print(", ".join(line_hymns))
    
    else:
        print("\n🎉 ALL HYMNS ARE PRESENT! Complete collection detected.")
    
    print("\n" + "=" * 60)

def save_missing_hymns_list(missing_hymns, output_file="missing_hymns.txt"):
    """
    Saves the list of missing hymns to a text file.
    
    Args:
        missing_hymns (set): Set of missing hymn numbers
        output_file (str): Output file name
    """
    if missing_hymns:
        with open(output_file, 'w') as f:
            f.write("Missing Hymns from TLH (1-668):\n")
            f.write("=" * 40 + "\n\n")
            
            missing_list = sorted(missing_hymns)
            for hymn_num in missing_list:
                f.write(f"Hymn {hymn_num}\n")
            
            f.write(f"\nTotal missing: {len(missing_hymns)} hymns\n")
        
        print(f"Missing hymns list saved to '{output_file}'")

def main():
    # Specify your JSON file path here
    json_file_path = input("Enter the path to your hymns JSON file: ").strip()
    
    if not json_file_path:
        json_file_path = "hymns.json"  # Default filename
        print(f"Using default filename: {json_file_path}")
    
    print(f"\nScanning hymns in: {json_file_path}")
    print("Looking for hymns numbered 1 through 668...")
    
    # Scan for missing hymns
    present_hymns, missing_hymns = scan_hymns_for_missing(json_file_path)
    
    if present_hymns or missing_hymns:
        # Print detailed report
        print_hymn_report(present_hymns, missing_hymns)
        
        # Ask if user wants to save missing hymns to file
        if missing_hymns:
            save_option = input("\nSave missing hymns list to file? (y/n): ").lower()
            if save_option == 'y':
                save_missing_hymns_list(missing_hymns)
    
    print("\nScan complete!")

if __name__ == "__main__":
    main()