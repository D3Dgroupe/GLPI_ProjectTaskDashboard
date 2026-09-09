const fs = require('fs');
const path = require('path');

const src = fs.readFileSync(path.join(__dirname, '..', 'js', 'projecttaskdashboard.js'), 'utf8');

const checks = [
  [src.includes("form[data-glpi-search-form]"), 'dashboard search form is bound'],
  [src.includes("submit.ptd"), 'Enter/submit is intercepted'],
  [src.includes("button[name=\"search\"]"), 'yellow Search button is intercepted'],
  [src.includes('serializeArray()') || src.includes('serialize()'), 'search criteria are serialized'],
  [src.includes('reloadWithParams') && src.includes('window.reloadTab'), 'search is routed through reloadTab'],
  [src.includes('.projecttaskdashboard-mytasks .savedsearches-item a'), 'global mytasks saved searches stay in plugin route'],
  [src.includes('dataset.mytasksTarget'), 'mytasks target is read from plugin root'],
  [src.includes('search_refresh.projecttaskdashboard'), 'mytasks project links are normalized after native AJAX refresh'],
];

const failures = checks.filter(([ok]) => !ok).map(([, message]) => message);
if (failures.length > 0) {
  console.error('FAIL: ' + failures.join('; '));
  process.exit(1);
}

console.log('search transport contract ok');
