/**
 * Oriental Mindoro address dropdowns (Town/City -> Barangay), reading from
 * the local window.CARESYNC_ADDRESS data (public/assets/js/data/
 * oriental-mindoro-address.js) instead of fetching PSGC Cloud - see
 * CARESYNC_PROMPT_LOCAL_ADDRESS_DATA.md. Scope is Oriental Mindoro only: 15
 * cities/municipalities, 426 barangays, all filtering/lookup is instant
 * (no network request, no debounce, no loading state).
 *
 * Usage: window.CareSyncPsgc.init()
 * Expects the same fixed field ids _step_applicant.php renders:
 *   city_search, address_city, city_municipality_code,
 *   barangay_search, address_barangay, barangay_code,
 * each city/barangay input's own .psgc-dropdown/.psgc-error living inside
 * its .psgc-field wrapper (same markup _step_applicant.php renders).
 * No-ops (returns null) if the page has no #city_search - not every page
 * using caresync-wizard.js necessarily has the address step wired in yet.
 */
window.CareSyncPsgc = (function () {
    function init() {
        const citySearch = document.getElementById('city_search');
        if (!citySearch) {
            return null;
        }

        const addressCityInput = document.getElementById('address_city');
        const cityCodeInput = document.getElementById('city_municipality_code');
        const cityDropdown = citySearch.closest('.psgc-field').querySelector('.psgc-dropdown');
        const cityError = document.querySelector('.psgc-error[data-field="city"]');

        const barangaySearch = document.getElementById('barangay_search');
        const addressBarangayInput = document.getElementById('address_barangay');
        const barangayCodeInput = document.getElementById('barangay_code');
        const barangayDropdown = barangaySearch.closest('.psgc-field').querySelector('.psgc-dropdown');
        const barangayError = document.querySelector('.psgc-error[data-field="barangay"]');

        let barangayOptions = [];
        let lastSelectedCityName = addressCityInput.value || '';
        let lastSelectedBarangayName = addressBarangayInput.value || '';

        function showDropdown(el) { el.classList.remove('d-none'); }
        function hideDropdown(el) { el.classList.add('d-none'); el.innerHTML = ''; }

        function renderEmpty(el, text) {
            el.innerHTML = '';
            const empty = document.createElement('div');
            empty.className = 'psgc-empty';
            empty.textContent = text;
            el.appendChild(empty);
            showDropdown(el);
        }

        function renderResults(el, results, onPick) {
            el.innerHTML = '';
            results.forEach(function (rowData) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action py-2';
                item.textContent = rowData.name;
                item.addEventListener('click', function () {
                    onPick(rowData);
                });
                el.appendChild(item);
            });
            showDropdown(el);
        }

        function clearFieldError(el) {
            el.classList.add('d-none');
            el.textContent = '';
        }

        function showFieldError(el, text) {
            el.textContent = text;
            el.classList.remove('d-none');
        }

        // The data file's own city.code is always 10-digit (matches what's
        // already saved in plan_holders.city_municipality_code/barangay_code -
        // checked directly against the live table before choosing this), so
        // every hidden field gets set to .code even when a match came in via
        // .old_code on preselect (a saved 9-digit legacy code, if any ever
        // existed, normalizes to 10-digit going forward).
        const address = window.CARESYNC_ADDRESS;
        if (!address || !Array.isArray(address.cities)) {
            console.error('CareSyncPsgc: window.CARESYNC_ADDRESS is missing or malformed.');
            showFieldError(cityError, 'Address list failed to load - refresh the page.');
            showFieldError(barangayError, 'Address list failed to load - refresh the page.');
            citySearch.disabled = true;
            barangaySearch.disabled = true;
            return { selectCity: function () {}, selectBarangay: function () {} };
        }

        // "City of Calapan" should match a search for "Calapan" - strip a
        // leading "City of " before comparing, both for the stored name and
        // whatever the user typed.
        function normalize(name) {
            return name.toLowerCase().replace(/^city of\s+/, '').trim();
        }

        const cities = address.cities.map(function (city) {
            return { code: city.code, name: city.name };
        });

        // code/old_code -> city (with its own .barangays), and
        // code/old_code -> {code, name, cityCode}, for preselect on an edit
        // page (which may hold either format) and for the city-mismatch
        // guard filterBarangays()/selectBarangay() don't need but the server
        // side (PsgcService) still checks at save time.
        const citiesByCode = {};
        const barangaysByCode = {};
        address.cities.forEach(function (city) {
            citiesByCode[city.code] = city;
            citiesByCode[city.old_code] = city;
            city.barangays.forEach(function (barangay) {
                const row = { code: barangay.code, name: barangay.name, cityCode: city.code };
                barangaysByCode[barangay.code] = row;
                barangaysByCode[barangay.old_code] = row;
            });
        });

        function searchCities(query) {
            const needle = normalize(query);
            const results = needle === ''
                ? cities
                : cities.filter(function (c) { return normalize(c.name).indexOf(needle) !== -1; });

            if (results.length === 0) {
                renderEmpty(cityDropdown, 'No matching town/city found.');
                return;
            }
            renderResults(cityDropdown, results, selectCity);
        }

        function selectCity(rowData) {
            citySearch.value = rowData.name;
            addressCityInput.value = rowData.name;
            cityCodeInput.value = rowData.code;
            lastSelectedCityName = rowData.name;
            clearFieldError(cityError);
            hideDropdown(cityDropdown);
            resetBarangay();
            loadBarangays(rowData.code);
        }

        function resetBarangay() {
            barangaySearch.value = '';
            barangaySearch.placeholder = 'Search barangay...';
            barangaySearch.disabled = true;
            addressBarangayInput.value = '';
            barangayCodeInput.value = '';
            lastSelectedBarangayName = '';
            barangayOptions = [];
            hideDropdown(barangayDropdown);
            clearFieldError(barangayError);
        }

        citySearch.addEventListener('input', function () {
            if (citySearch.value !== lastSelectedCityName) {
                addressCityInput.value = '';
                cityCodeInput.value = '';
            }
            searchCities(citySearch.value.trim());
        });

        citySearch.addEventListener('focus', function () {
            searchCities(citySearch.value.trim());
        });

        function loadBarangays(cityCode, preselectCode) {
            const city = citiesByCode[cityCode];
            barangayOptions = city
                ? city.barangays.map(function (b) { return { code: b.code, name: b.name }; })
                : [];
            barangaySearch.disabled = false;
            barangaySearch.placeholder = 'Search barangay...';
            hideDropdown(barangayDropdown);

            if (preselectCode) {
                const match = barangayOptions.find(function (b) { return b.code === preselectCode; })
                    || (barangaysByCode[preselectCode] && barangaysByCode[preselectCode].cityCode === cityCode
                        ? barangaysByCode[preselectCode]
                        : null);
                if (match) {
                    selectBarangay(match);
                }
            }
        }

        function filterBarangays(query) {
            const needle = query.trim().toLowerCase();
            const results = needle === ''
                ? barangayOptions
                : barangayOptions.filter(function (b) { return b.name.toLowerCase().indexOf(needle) !== -1; });

            if (results.length === 0) {
                renderEmpty(barangayDropdown, 'No matching barangay found.');
                return;
            }
            renderResults(barangayDropdown, results, selectBarangay);
        }

        function selectBarangay(rowData) {
            barangaySearch.value = rowData.name;
            addressBarangayInput.value = rowData.name;
            barangayCodeInput.value = rowData.code;
            lastSelectedBarangayName = rowData.name;
            clearFieldError(barangayError);
            hideDropdown(barangayDropdown);
        }

        barangaySearch.addEventListener('input', function () {
            if (barangaySearch.value !== lastSelectedBarangayName) {
                addressBarangayInput.value = '';
                barangayCodeInput.value = '';
            }
            filterBarangays(barangaySearch.value);
        });

        barangaySearch.addEventListener('focus', function () {
            if (!barangaySearch.disabled) {
                filterBarangays(barangaySearch.value);
            }
        });

        document.addEventListener('click', function (event) {
            if (!citySearch.closest('.psgc-field').contains(event.target)) {
                hideDropdown(cityDropdown);
            }
            if (!barangaySearch.closest('.psgc-field').contains(event.target)) {
                hideDropdown(barangayDropdown);
            }
        });

        const savedCityCode = cityCodeInput.value.trim();
        const savedBarangayCode = barangayCodeInput.value.trim();
        if (savedCityCode !== '') {
            const savedCity = citiesByCode[savedCityCode];
            if (savedCity) {
                // Normalize a legacy 9-digit old_code to the canonical
                // 10-digit code, same as a fresh selection would.
                cityCodeInput.value = savedCity.code;
                barangaySearch.value = lastSelectedBarangayName;
                loadBarangays(savedCity.code, savedBarangayCode || null);
            } else {
                barangaySearch.placeholder = 'Select a Town/City first';
            }
        } else {
            barangaySearch.placeholder = 'Select a Town/City first';
        }

        return { selectCity: selectCity, selectBarangay: selectBarangay };
    }

    return { init: init };
})();
