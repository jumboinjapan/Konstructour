const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../js/admin-dashboard.js'), 'utf8');
function definition(name, nextMarker) {
  const start = source.indexOf(`function ${name}(`);
  assert.ok(start >= 0);
  return source.slice(start, source.indexOf(nextMarker, start));
}
const nodes = [];
const context = vm.createContext({
  document: { createElement: () => ({style: {}}), body: {appendChild: (node) => nodes.push(node)} },
  setTimeout: () => {},
});
vm.runInContext(definition('escapeHtml', '// Load section'), context);
vm.runInContext(definition('showError', '// Start realtime updates'), context);
const payload = '<img src="data:," onerror="globalThis.syntheticExecuted=true">';
test('escapes API log messages after coercion, including JSON arrays', () => {
  for (const value of [payload, [payload], [[payload]], {toString: () => payload}]) {
    const output = context.escapeHtml(value);
    assert.ok(!output.includes('<img'), 'message must remain text');
    assert.ok(output.includes('&lt;img'));
  }
  assert.equal(context.escapeHtml('A & B'), 'A &amp; B');
  assert.equal(context.escapeHtml(42), '42');
  assert.equal(context.escapeHtml(null), 'null');
});
test('error notification treats upstream error text as text', () => {
  context.showError(payload);
  assert.ok(!nodes.at(-1).innerHTML.includes('<img'));
  assert.ok(nodes.at(-1).innerHTML.includes('&lt;img'));
});
