const fs = require('fs');
const path = require('path');
const src = fs.readFileSync(path.join(__dirname, '..', 'js', 'projecttaskdashboard.js'), 'utf8');

for (const needle of [
  'Project$main',
  'ProjectTask$',
  'DashboardTab$1',
  'normalizeProjectTabs',
  'normalizeMyTasksProjectLinks',
  'projecttaskdashboard-mytasks .savedsearches-item a',
  'search_refresh.projecttaskdashboard',
]) {
  if (!src.includes(needle)) throw new Error('missing ' + needle);
}
if (src.includes('Tâches de projet')) {
  throw new Error('must not match translated labels');
}
console.log('project tabs contract ok');
