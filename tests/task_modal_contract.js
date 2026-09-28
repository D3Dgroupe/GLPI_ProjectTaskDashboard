// Issue #22: tasks opened from the dashboard / "Mes tâches" tables open in a
// modal (GLPI's own _in_modal task form in an iframe) instead of navigating
// away, so the list position is kept.
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

for (const needle of [
  "url.searchParams.set('_in_modal', '1')",
  'Ouvrir la fiche complète',
  'event.ctrlKey || event.metaKey',
  'if (loads > 1)',
  'refreshDashboardTable(root, table)',
]) {
  if (!js.includes(needle)) fail(`JS must contain ${needle}`);
}
if (!css.includes('#ptd-task-modal .ptd-task-modal-frame')) fail('CSS must size the modal iframe');

const $stub = function () { return { on() { return this; } }; };
const sandbox = { window: { location: { origin: 'https://glpi.test' } }, document: {}, jQuery: $stub, URL };
vm.createContext(sandbox);
vm.runInContext(js, sandbox);
const api = sandbox.window.ProjectTaskDashboardTaskModal;
if (!api) fail('JS must expose ProjectTaskDashboardTaskModal');

const u = api.url('/front/projecttask.form.php?id=42&forcetab=ProjectTask$main');
if (u !== 'https://glpi.test/front/projecttask.form.php?id=42&_in_modal=1') fail('modal url: ' + u);
if (api.url('/front/project.form.php?id=3') !== null) fail('only task links open in the modal');
if (api.url('/front/projecttask.form.php') !== null) fail('a task link without id is left alone');

console.log('task modal contract ok');
