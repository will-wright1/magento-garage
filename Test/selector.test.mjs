import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import {setImmediate} from 'node:timers/promises';
import test from 'node:test';
import assert from 'node:assert/strict';
import {JSDOM} from '../dev/js/node_modules/jsdom/lib/api.js';

const source = readFileSync(new URL('../view/frontend/web/js/vehicle-selector.js', import.meta.url), 'utf8');
const option = (value, label = value) => ({value, label});

function options(url) {
    const query = new URL(url).searchParams;
    switch (query.get('level')) {
        case 'make': return [option('Ford'), option('Toyota')];
        case 'model': return [option(query.get('make') === 'Ford' ? 'Ranger' : 'Hilux')];
        case 'year': return [option('2020'), option('2021')];
        case 'variant': return [option(query.get('market') === 'US' ? '17' : (query.get('make') === 'Ford' ? '7' : '9'), 'Double Cab')];
        default: return [];
    }
}

function fixture(config = {}, fetcher = async url => ({ok: true, json: async () => ({options: options(url)})})) {
    const dom = new JSDOM(`<form><fieldset>
        <select name="vehicle_market"><option value="GB" ${config.market === 'US' ? '' : 'selected'}>UK</option><option value="US" ${config.market === 'US' ? 'selected' : ''}>US</option></select>
        <div data-identification-fields><select name="registration_country"><option value="GB">UK</option><option value="US">US</option></select><input name="registration"><div data-region-fields><select name="registration_region"><option value="">Choose</option><option value="CA">California</option><option value="NY">New York</option></select></div></div>
        <div id="selector" class="garage-vehicle-selector">
        <input type="hidden" name="vehicle_type_id" data-vehicle-type value="">
        <div data-catalogue-toggle hidden><input type="checkbox" data-use-catalogue></div>
        <div data-catalogue-fields hidden>${['make', 'model', 'year', 'variant'].map(level =>
            `<div data-field="${level}"><select data-level="${level}" disabled><option value="">Choose</option></select></div>`).join('')}</div>
        <p data-selector-status role="status"></p>
        <div data-manual-fields><div data-field="make"><input name="make" required></div><div data-field="model"><input name="model" required></div><div data-field="year"><input name="model_year"></div></div>
        </div></fieldset><button type="submit">Save</button></form>`, {url: 'https://shop.example/garage/account/index'});
    let initialise;
    vm.runInNewContext(source, {
        define: (dependencies, factory) => { initialise = factory(text => text); },
        URL, window: dom.window, Option: dom.window.Option, fetch: fetcher
    });
    const element = dom.window.document.querySelector('#selector');
    initialise({url: 'https://shop.example/garage/catalog/options', make: '', model: '', year: '', typeId: '',
        preferCatalogue: true, ...config}, element);
    const control = level => element.querySelector(`[data-level="${level}"]`);
    const change = (level, value) => {
        control(level).value = value;
        control(level).dispatchEvent(new dom.window.Event('change', {bubbles: true}));
    };
    return {dom, element, control, change, typeId: element.querySelector('[data-vehicle-type]'),
        toggle: element.querySelector('[data-use-catalogue]'), status: element.querySelector('[data-selector-status]')};
}

async function until(predicate) {
    for (let attempt = 0; attempt < 100; attempt++) {
        if (predicate()) { return; }
        await setImmediate();
    }
    assert.fail('Selector did not reach the expected state');
}

test('editing restores the exact saved canonical selection and disables manual fields', async () => {
    const form = fixture({make: 'Ford', model: 'Ranger', year: '2020', typeId: '7'});
    await until(() => form.typeId.value === '7');
    assert.equal(form.control('make').value, 'Ford');
    assert.equal(form.control('year').value, '2020');
    assert.ok(form.element.querySelector('[name="make"]').disabled);
    assert.equal(form.control('variant').required, true);
    form.dom.window.close();
});

test('changing make clears the old type until model year and variant are selected', async () => {
    const form = fixture({make: 'Ford', model: 'Ranger', year: '2020', typeId: '7'});
    await until(() => form.typeId.value === '7');
    form.change('make', 'Toyota');
    assert.equal(form.typeId.value, '');
    await until(() => form.control('model').options.length === 2);
    form.change('model', 'Hilux');
    await until(() => form.control('year').options.length === 3);
    form.change('year', '2020');
    await until(() => form.control('variant').options.length === 2);
    assert.equal(form.typeId.value, '');
    form.change('variant', '9');
    assert.equal(form.typeId.value, '9');
    form.dom.window.close();
});

test('a slow earlier response cannot overwrite options for the newer make', async () => {
    let releaseFord;
    const form = fixture({}, async url => {
        const query = new URL(url).searchParams;
        if (query.get('level') === 'model' && query.get('make') === 'Ford') {
            await new Promise(resolve => { releaseFord = resolve; });
        }
        return {ok: true, json: async () => ({options: options(url)})};
    });
    await until(() => form.control('make').options.length === 3);
    form.change('make', 'Ford');
    await until(() => Boolean(releaseFord));
    form.change('make', 'Toyota');
    await until(() => form.control('model').options[1]?.value === 'Hilux');
    releaseFord();
    await setImmediate();
    await setImmediate();
    assert.equal(form.control('model').options[1].value, 'Hilux');
    assert.equal(form.control('make').value, 'Toyota');
    form.dom.window.close();
});

test('request failures restore manual entry and remove a stale catalogue ID', async () => {
    const form = fixture({}, async () => { throw new Error('Network unavailable'); });
    await until(() => form.status.textContent.includes('could not be loaded'));
    assert.equal(form.toggle.checked, false);
    assert.equal(form.element.querySelector('[name="make"]').disabled, false);
    assert.equal(form.element.querySelector('[data-manual-fields]').hidden, false);
    assert.equal(form.typeId.value, '');
    assert.equal(form.control('variant').required, false);
    form.dom.window.close();
});

test('legacy manual vehicles do not automatically acquire a fitment association', async () => {
    let requests = 0;
    const form = fixture({preferCatalogue: false, make: 'Ford', model: 'Ranger'}, async url => {
        requests++;
        return {ok: true, json: async () => ({options: options(url)})};
    });
    await setImmediate();
    assert.equal(requests, 0);
    assert.equal(form.typeId.value, '');
    assert.equal(form.element.querySelector('[name="make"]').disabled, false);
    form.dom.window.close();
});

test('submitting an incomplete catalogue selection is blocked', async () => {
    const form = fixture();
    await until(() => form.control('make').options.length === 3);
    const submit = new form.dom.window.Event('submit', {cancelable: true});
    form.element.closest('form').dispatchEvent(submit);
    assert.equal(submit.defaultPrevented, true);
    assert.match(form.status.textContent, /Choose all vehicle options/);
    form.dom.window.close();
});

test('option labels are displayed as text without introducing HTML elements', async () => {
    const label = '<img src=x onerror=alert(1)>';
    const form = fixture({}, async () => ({ok: true, json: async () => ({options: [option('Ford', label)]})}));
    await until(() => form.control('make').options.length === 2);
    assert.equal(form.control('make').options[1].textContent, label);
    assert.equal(form.element.querySelectorAll('img').length, 0);
    form.dom.window.close();
});

test('US catalogue restores year-first selection and every request includes the specification market', async () => {
    const calls = [];
    const form = fixture({market: 'US', make: 'Ford', model: 'Ranger', year: '2020', typeId: '17'}, async url => {
        calls.push(new URL(url).searchParams);
        return {ok: true, json: async () => ({options: options(url)})};
    });
    await until(() => form.typeId.value === '17');
    assert.deepEqual(calls.map(query => query.get('level')), ['year', 'make', 'model', 'variant']);
    assert.ok(calls.every(query => query.get('market') === 'US'));
    assert.equal(calls[1].get('year'), '2020');
    assert.equal(form.element.querySelector('[data-catalogue-fields]').firstElementChild.dataset.field, 'year');
    assert.equal(form.element.querySelector('[name="model_year"]').required, true);
    form.dom.window.close();
});

test('switching market clears the old association without changing plate issuing country', async () => {
    const form = fixture({make: 'Ford', model: 'Ranger', year: '2020', typeId: '7'});
    await until(() => form.typeId.value === '7');
    const market = form.element.closest('form').querySelector('[name="vehicle_market"]');
    market.value = 'US'; market.dispatchEvent(new form.dom.window.Event('change'));
    assert.equal(form.typeId.value, '');
    await until(() => form.control('year').options.length === 3);
    assert.equal(form.control('make').disabled, true);
    assert.equal(form.element.closest('form').querySelector('[name="registration_country"]').value, 'GB');
    form.dom.window.close();
});

test('an earlier UK response cannot overwrite the year-first US market options', async () => {
    let release;
    const form = fixture({}, async url => {
        const query = new URL(url).searchParams;
        if (query.get('market') === 'GB' && query.get('level') === 'model') {
            await new Promise(resolve => { release = resolve; });
        }
        return {ok: true, json: async () => ({options: options(url)})};
    });
    await until(() => form.control('make').options.length === 3);
    form.change('make', 'Ford'); await until(() => Boolean(release));
    const market = form.element.closest('form').querySelector('[name="vehicle_market"]');
    market.value = 'US'; market.dispatchEvent(new form.dom.window.Event('change'));
    await until(() => form.control('year').options.length === 3);
    release(); await setImmediate(); await setImmediate();
    assert.equal(form.control('model').options.length, 1);
    assert.equal(form.control('year').options.length, 3);
    assert.equal(form.typeId.value, '');
    form.dom.window.close();
});

test('US plate jurisdiction is required only when a plate is entered and clears on country change', async () => {
    const form = fixture({preferCatalogue: false});
    const document = form.dom.window.document;
    const country = document.querySelector('[name="registration_country"]');
    const plate = document.querySelector('[name="registration"]');
    const region = document.querySelector('[name="registration_region"]');
    assert.equal(plate.required, true); assert.equal(region.disabled, true);
    country.value = 'US'; country.dispatchEvent(new form.dom.window.Event('change'));
    assert.equal(plate.required, false); assert.equal(region.required, false);
    plate.value = 'ABC123'; plate.dispatchEvent(new form.dom.window.Event('input'));
    assert.equal(region.required, true); assert.equal(region.disabled, false);
    region.value = 'CA';
    country.value = 'GB'; country.dispatchEvent(new form.dom.window.Event('change'));
    assert.equal(region.value, ''); assert.equal(region.disabled, true); assert.equal(plate.required, true);
    form.dom.window.close();
});

test('merchant plate requirement overrides and manual-only US catalogues retain usable controls', async () => {
    let calls = 0;
    const form = fixture({market: 'US', catalogueAvailable: false, plateRequirement: 'optional'}, async () => { calls++; });
    await setImmediate();
    assert.equal(calls, 0); assert.equal(form.toggle.checked, false);
    assert.equal(form.element.querySelector('[name="model_year"]').required, true);
    assert.equal(form.element.querySelector('[name="model_year"]').disabled, false);
    assert.equal(form.dom.window.document.querySelector('[name="registration"]').required, false);
    form.dom.window.close();
});

test('a market with no definitions falls back to manual entry without retaining a type', async () => {
    const form = fixture({market: 'US'}, async () => ({ok: true, json: async () => ({options: []})}));
    await until(() => form.status.textContent.includes('No vehicle definitions'));
    assert.equal(form.toggle.checked, false); assert.equal(form.typeId.value, '');
    assert.equal(form.element.querySelector('[data-manual-fields]').hidden, false);
    form.dom.window.close();
});
