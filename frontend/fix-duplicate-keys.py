import glob, re, os
for f in glob.glob('/home/kwat0g/Desktop/ogamiPHP/frontend/src/hooks/*.ts'):
    with open(f, 'r') as fp: content = fp.read()
    new_content = []
    lines = content.split('\n')
    i = 0
    removed = 0
    while i < len(lines):
        line = lines[i]
        if re.match(r'^\s*enabled,\s*$', line):
            has_explicit = False
            for j in range(i+1, min(i+15, len(lines))):
                if 'useQuery' in lines[j] or 'export function' in lines[j]: break
                if re.match(r'^\s*enabled\s*[:]', lines[j]):
                    has_explicit = True
                    break
            
            if not has_explicit:
                # Check backwards as well
                for j in range(i-1, max(i-15, -1), -1):
                    if 'useQuery' in lines[j] or 'export function' in lines[j]: break
                    if re.match(r'^\s*enabled\s*[:]', lines[j]):
                        has_explicit = True
                        break

            if has_explicit:
                print(f"Removed duplicate 'enabled,' in {os.path.basename(f)}")
                removed += 1
                i += 1
                continue
        
        new_content.append(line)
        i += 1
        
    if removed > 0:
        with open(f, 'w') as fp:
            fp.write('\n'.join(new_content))
