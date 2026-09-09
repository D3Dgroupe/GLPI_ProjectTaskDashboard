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
if (!src.includes("searchParams.getAll('forcetab')")) {
  throw new Error('getForcetab must inspect all forcetab parameters');
}
if (!src.includes('forcetabs[forcetabs.length - 1]')) {
  throw new Error('getForcetab must use the last forcetab parameter emitted by GLPI');
}
console.log('project tabs contract ok');
