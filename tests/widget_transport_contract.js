const fs = require('fs');
const path = require('path');

const src = fs.readFileSync(path.join(__dirname, '..', 'templates', 'dashboard', 'widgets.html.twig'), 'utf8');

if (!src.includes("javascript:reloadTab('")) {
  console.error('FAIL: widgets do not use GLPI reloadTab directly');
  process.exit(1);
}

console.log('widget transport contract ok');
