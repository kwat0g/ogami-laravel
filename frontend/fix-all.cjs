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
  if (content.includes('try {') && content.includes('setShowForm(false)') && !content.includes('catch (')) {
    content = content.replace(/setShowForm\(false\)\n  \}/g, 'setShowForm(false)\n    } catch (e) {\n      console.error(e)\n    }');
    fs.writeFileSync(file, content);
    console.log('Fixed', file);
  }
});
