const responseCache = new Map();
const addressPrompt = 'Select your region, province, city or municipality, and barangay to complete the address.';

function fetchLocations(url) {
    if (!responseCache.has(url)) {
        const request = fetch(url, {
            headers: { Accept: 'application/json' },
        }).then((response) => {
            if (!response.ok) {
                throw new Error(`Address service returned ${response.status}.`);
            }

            return response.json();
        }).then((payload) => Array.isArray(payload) ? payload : (payload.data ?? []));

        responseCache.set(url, request.catch((error) => {
            responseCache.delete(url);
            throw error;
        }));
    }

    return responseCache.get(url);
}

function replaceOptions(select, placeholder, locations = []) {
    select.replaceChildren();
    select.add(new Option(placeholder, ''));

    locations.forEach((location) => {
        const option = new Option(location.name.trim(), location.code);
        option.dataset.name = location.name.trim();
        select.add(option);
    });
}

function resetSelect(select, placeholder) {
    replaceOptions(select, placeholder);
    select.disabled = true;
}

function selectedName(select) {
    return select.selectedOptions[0]?.dataset.name ?? '';
}

document.querySelectorAll('[data-psgc-address]').forEach((finder) => {
    const output = finder.parentElement?.querySelector('[data-psgc-address-output]');
    const preview = finder.querySelector('[data-psgc-address-preview]');
    const addressDetail = finder.querySelector('[data-psgc-address-detail]');
    const region = finder.querySelector('[data-psgc-region]');
    const province = finder.querySelector('[data-psgc-province]');
    const city = finder.querySelector('[data-psgc-city]');
    const barangay = finder.querySelector('[data-psgc-barangay]');
    const status = finder.querySelector('[data-psgc-status]');
    const retry = finder.querySelector('[data-psgc-retry]');
    const apiBase = finder.dataset.apiBase;

    if (!output || !preview || !addressDetail || !region || !province || !city || !barangay || !status || !retry || !apiBase) {
        return;
    }

    const showFailure = () => {
        status.hidden = false;
        status.textContent = 'The address directory is temporarily unavailable.';
        status.classList.add('text-red-700');
        retry.hidden = false;
    };

    const clearAddress = () => {
        output.value = '';
        preview.textContent = addressPrompt;
        barangay.required = output.dataset.required === 'true';
        output.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const writeAddress = () => {
        if (!barangay.value) {
            output.value = '';
            preview.textContent = addressPrompt;
            output.dispatchEvent(new Event('input', { bubbles: true }));

            return;
        }

        const barangayName = selectedName(barangay);
        const address = [
            addressDetail.value.trim(),
            barangayName ? `Barangay ${barangayName}` : '',
            selectedName(city),
            selectedName(province),
        ].filter(Boolean).join(', ');

        output.value = address;
        preview.textContent = address || addressPrompt;
        output.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const loadRegions = async () => {
        resetSelect(province, 'Select a region first');
        resetSelect(city, 'Select a province first');
        resetSelect(barangay, 'Select a city or municipality first');
        region.disabled = true;
        replaceOptions(region, 'Loading regions...');
        status.hidden = false;
        status.textContent = 'Loading address options...';
        status.classList.remove('text-red-700');
        retry.hidden = true;

        try {
            const regions = await fetchLocations(`${apiBase}/regions`);
            replaceOptions(region, 'Select a region', regions);
            region.disabled = false;
            status.hidden = true;
        } catch {
            replaceOptions(region, 'Address directory unavailable');
            showFailure();
        }
    };

    region.addEventListener('change', async () => {
        clearAddress();
        resetSelect(province, 'Select a region first');
        resetSelect(city, 'Select a province first');
        resetSelect(barangay, 'Select a city or municipality first');

        if (!region.value) {
            return;
        }

        replaceOptions(province, 'Loading provinces...');
        status.hidden = false;
        status.textContent = 'Loading provinces...';

        try {
            const provinces = await fetchLocations(`${apiBase}/regions/${encodeURIComponent(region.value)}/provinces`);

            if (provinces.length === 0) {
                replaceOptions(province, 'No province for this region');
                await loadCities(`${apiBase}/regions/${encodeURIComponent(region.value)}/cities-municipalities`);
                return;
            }

            replaceOptions(province, 'Select a province', provinces);
            const independentOption = new Option('Independent city / no province', '__region__');
            independentOption.dataset.name = '';
            province.add(independentOption);
            province.disabled = false;
            status.hidden = true;
        } catch {
            showFailure();
        }
    });

    const loadCities = async (url) => {
        resetSelect(barangay, 'Select a city or municipality first');
        replaceOptions(city, 'Loading cities and municipalities...');
        city.disabled = true;
        status.hidden = false;
        status.textContent = 'Loading cities and municipalities...';

        try {
            const cities = await fetchLocations(url);
            replaceOptions(city, 'Select a city or municipality', cities);
            city.disabled = false;
            status.hidden = true;
        } catch {
            showFailure();
        }
    };

    province.addEventListener('change', () => {
        clearAddress();
        resetSelect(city, 'Select a province first');
        resetSelect(barangay, 'Select a city or municipality first');

        if (!province.value) {
            return;
        }

        const url = province.value === '__region__'
            ? `${apiBase}/regions/${encodeURIComponent(region.value)}/cities-municipalities`
            : `${apiBase}/provinces/${encodeURIComponent(province.value)}/cities-municipalities`;
        loadCities(url);
    });

    city.addEventListener('change', async () => {
        clearAddress();
        resetSelect(barangay, 'Select a city or municipality first');

        if (!city.value) {
            return;
        }

        replaceOptions(barangay, 'Loading barangays...');
        status.hidden = false;
        status.textContent = 'Loading barangays...';

        try {
            const barangays = await fetchLocations(`${apiBase}/cities-municipalities/${encodeURIComponent(city.value)}/barangays`);
            replaceOptions(barangay, 'Select a barangay', barangays);
            barangay.disabled = false;
            status.hidden = true;
        } catch {
            showFailure();
        }
    });

    barangay.addEventListener('change', writeAddress);
    addressDetail.addEventListener('input', writeAddress);
    retry.addEventListener('click', loadRegions);
    loadRegions();
});
