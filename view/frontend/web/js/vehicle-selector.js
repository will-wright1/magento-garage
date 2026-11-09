define(['mage/translate'], function ($t) {
    'use strict';

    return function (config, element) {
        var form = element.matches('form') ? element : element.closest('form'),
            marketControl = form.querySelector('[name="vehicle_market"]'),
            market = marketControl ? marketControl.value : (config.market || 'GB'),
            levels,
            controls = {},
            toggle = element.querySelector('[data-use-catalogue]'),
            manual = element.querySelector('[data-manual-fields]'),
            catalogue = element.querySelector('[data-catalogue-fields]'),
            typeId = element.querySelector('[data-vehicle-type]'),
            status = element.querySelector('[data-selector-status]'),
            revision = 0;

        ['make', 'model', 'year', 'variant'].forEach(function (name) {
            controls[name] = element.querySelector('[data-level="' + name + '"]');
        });

        function arrange() {
            levels = market === 'US' ? ['year', 'make', 'model', 'variant'] : ['make', 'model', 'year', 'variant'];
            [catalogue, manual].forEach(function (container) {
                levels.forEach(function (name) {
                    var field = container.querySelector('[data-field="' + name + '"]');
                    if (field) { container.appendChild(field); }
                });
            });
            var identity = form.querySelector('[data-identification-fields]'),
                selector = form.querySelector('.garage-vehicle-selector'),
                year = manual.querySelector('[name="model_year"]');
            if (identity && selector) {
                if (market === 'US') { identity.parentNode.insertBefore(selector, identity); }
                else { selector.parentNode.insertBefore(identity, selector); }
            }
            if (year) { year.required = market === 'US'; }
        }

        function identification() {
            var country = form.querySelector('[name="registration_country"]'),
                plate = form.querySelector('[name="registration"]'),
                region = form.querySelector('[name="registration_region"]'),
                regionFields = form.querySelector('[data-region-fields]');
            if (!country || !plate || !region || !regionFields) { return; }
            var us = country.value === 'US';
            plate.required = config.plateRequirement === 'required' ||
                (config.plateRequirement !== 'optional' && config.plateRequirement !== 'required' && !us);
            regionFields.hidden = !us;
            region.disabled = !us;
            region.required = us && plate.value.trim() !== '';
            if (!us) { region.value = ''; }
        }

        function clear(index) {
            var control = controls[levels[index]];
            control.replaceChildren(new Option($t('Choose an option'), ''));
            control.disabled = true;
        }

        async function fill(index, seeds, ticket) {
            var url = new URL(config.url, window.location.origin), name = levels[index];
            url.searchParams.set('level', name);
            url.searchParams.set('market', market);
            url.searchParams.set('make', controls.make.value);
            url.searchParams.set('model', controls.model.value);
            url.searchParams.set('year', controls.year.value);
            var response = await fetch(url.toString(), {credentials: 'same-origin'});
            if (!response.ok) { throw new Error('Selector request failed'); }
            var result = await response.json();
            if (ticket !== revision) { return false; }
            if (!Array.isArray(result.options)) { throw new Error('Invalid selector response'); }
            clear(index);
            if (index === 0 && result.options.length === 0) {
                toggle.checked = false;
                setMode(false);
                status.textContent = $t('No vehicle definitions are available for this market. Enter details manually.');
                return false;
            }
            result.options.forEach(function (option) {
                if (typeof option.value !== 'string' || typeof option.label !== 'string') {
                    throw new Error('Invalid vehicle option');
                }
                controls[name].add(new Option(option.label, option.value));
            });
            controls[name].disabled = false;
            controls[name].value = seeds ? (seeds[name] || '') : '';
            return true;
        }

        async function update(index, seeds) {
            var ticket = ++revision;
            typeId.value = '';
            for (var reset = index; reset < levels.length; reset++) { clear(reset); }
            status.textContent = $t('Loading vehicle options…');
            try {
                for (var next = index; next < levels.length; next++) {
                    if (next > 0 && !controls[levels[next - 1]].value) { break; }
                    if (!await fill(next, seeds, ticket)) { return; }
                }
                if (ticket === revision) {
                    typeId.value = controls.variant.value;
                    status.textContent = '';
                }
            } catch (error) {
                if (ticket !== revision) { return; }
                toggle.checked = false;
                setMode(false);
                status.textContent = $t('Vehicle options could not be loaded. Enter details manually or try the catalogue again.');
            }
        }

        function setMode(enabled, seeds) {
            ++revision;
            catalogue.hidden = !enabled;
            manual.hidden = enabled;
            manual.querySelectorAll('input').forEach(function (input) { input.disabled = enabled; });
            levels.forEach(function (name) { controls[name].required = enabled; controls[name].disabled = !enabled; });
            typeId.value = '';
            if (enabled) { update(0, seeds); }
            else { status.textContent = ''; }
        }

        Object.keys(controls).forEach(function (name) {
            controls[name].addEventListener('change', function () {
                var index = levels.indexOf(name);
                if (name === 'variant') { typeId.value = controls.variant.value; }
                else { update(index + 1); }
            });
        });
        toggle.addEventListener('change', function () { setMode(toggle.checked); });
        if (marketControl) {
            marketControl.addEventListener('change', function () {
                market = marketControl.value;
                arrange();
                setMode(toggle.checked);
            });
        }
        ['registration_country', 'registration'].forEach(function (name) {
            var input = form.querySelector('[name="' + name + '"]');
            if (input) { input.addEventListener(name === 'registration' ? 'input' : 'change', identification); }
        });
        form.addEventListener('submit', function (event) {
            if (toggle.checked && !typeId.value) {
                event.preventDefault();
                status.textContent = $t('Choose all vehicle options before saving.');
                var missing = levels.find(function (name) { return !controls[name].value; });
                if (missing) { controls[missing].focus(); }
            }
        });
        arrange();
        identification();
        var available = config.catalogueAvailable !== false;
        element.querySelector('[data-catalogue-toggle]').hidden = !available;
        toggle.checked = available && Boolean(config.preferCatalogue);
        setMode(toggle.checked, {make: config.make, model: config.model, year: config.year, variant: config.typeId});
    };
});
