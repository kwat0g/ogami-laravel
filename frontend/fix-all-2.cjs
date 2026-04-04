const fs = require('fs');
const path = require('path');

function getFiles(dir) {
  let results = [];
  const list = fs.readdirSync(dir);
  list.forEach(file => {
    file = path.join(dir, file);
    const stat = fs.statSync(file);
    if (stat && stat.isDirectory()) { 
      results = results.concat(getFiles(file));
    } else { 
      if (file.endsWith('.tsx')) results.push(file);
    }
  });
  return results;
}

const files = getFiles('src/pages');
files.forEach(file => {
  let content = fs.readFileSync(file, 'utf8');
  // Match `  }` that occurs after a try block before `  return (`
  let changed = false;
  let matches = content.match(/try \{\n(?:(?!catch\b)[\s\S])*?\n  \}\n\n  return \(/g);
  if (matches) {
    matches.forEach(match => {
      content = content.replace(match, match.replace(/\n  \}\n\n  return \(/, '\n    } catch (e) { console.error(e); }\n  }\n\n  return ('));
      changed = true;
    });
  }
  if (changed) {
    fs.writeFileSync(file, content);
    console.log('Fixed', file);
  }
});
