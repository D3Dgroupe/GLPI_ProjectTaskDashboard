// Issue #20: in the dashboard / "Mes tâches" tables, the État cell gets the
// background color configured on its ProjectState (Configuration >
// Intitulés > Statuts de projet), and the whole row too when that state is
// flagged "État terminé". Runs the real JS against a minimal DOM stub.
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.join(__dirname, '..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const fail = (msg) => { console.error('FAIL: ' + msg); process.exit(1); };

const js = read('js/projecttaskdashboard.js');
if (js !== read('public/js/projecttaskdashboard.js')) fail('root/public JS copies must stay identical');
const css = read('css/projecttaskdashboard.css');
if (css !== read('public/css/projecttaskdashboard.css')) fail('root/public CSS copies must stay identical');

for (const [file, needle] of [
  ['src/DashboardRenderer.php', 'statePalette->dataAttribute()'],
  ['src/MyTasksRenderer.php', 'statePalette->dataAttribute()'],
  ['src/ProjectStatePalette.php', 'data-ptd-state-palette'],
  ['src/ProjectStatePalette.php', "'is_finished'"],
  ['src/ProjectStatePalette.php', 'Toolbox::getFgColor'],
  ['css/projecttaskdashboard.css', 'td.ptd-state-cell'],
  ['css/projecttaskdashboard.css', 'tr.ptd-row-finished > td'],
  ['css/projecttaskdashboard.css', 'var(--ptd-state-bg)'],
  ['js/projecttaskdashboard.js', "'search_refresh.projecttaskdashboard-states'"],
]) {
  if (!read(file).includes(needle)) fail(`${file} must contain ${needle}`);
}

// The pure matching helper is exposed for tests.
const $stub = function () { return { on() { return this; } }; };
const sandbox = { window: {}, document: {}, jQuery: $stub };
vm.createContext(sandbox);
vm.runInContext(js, sandbox);
const match = sandbox.window.ProjectTaskDashboardStates && sandbox.window.ProjectTaskDashboardStates.match;
if (typeof match !== 'function') fail('JS must expose ProjectTaskDashboardStates.match');

const palette = [
  { name: 'En cours', bg: '#0000ff', fg: '#ffffff', finished: false },
  { name: 'Terminé', bg: '#00ff00', fg: '#003300', finished: true },
];
if (match(palette, '  Terminé ')?.finished !== true) fail('state must be matched by its displayed name');
if (match(palette, 'En cours')?.bg !== '#0000ff') fail('non-finished state must still be matched (État cell color)');
if (match(palette, 'Inconnu') !== null) fail('unknown state must not be painted');

console.log('table state colors contract ok');
