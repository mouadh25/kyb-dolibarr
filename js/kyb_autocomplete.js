/**
 * KYB Sovereign Autocomplete & Form Enrichment Engine for Dolibarr
 *
 * Implements real-time corporate entity autocomplete (Linear / Shadcn monochromatic UX)
 * with strict Personne Physique (PP) exclusion under Algerian Law 18-07 & Law 25-11.
 *
 * @package   KYB Dolibarr
 * @author    Mouadh Benhassine (Digidev Agency)
 * @copyright 2026 Digidev Agency
 * @license   GPL-3.0-or-later
 */

(function($) {
    'use strict';

    if (typeof $ === 'undefined') {
        console.warn('[KYB] jQuery is required for KYB Autocomplete');
        return;
    }

    $(document).ready(function() {
        var cfg = window.KYB_CFG || {};
        var ajaxUrl = cfg.ajaxUrl || '/custom/kybdolibarr/ajax/resolve.php';
        var token = cfg.token || '';
        var labels = cfg.labels || {};

        var $nameInput = $('input[name="name"], input[name="nom"], #name').first();
        var $nifInput = $('input[name="idprof3"]').first();
        var $rcInput = $('input[name="idprof1"]').first();

        if ($nameInput.length === 0) {
            return;
        }

        // Disable standard browser autocomplete to avoid visual overlap
        $nameInput.attr('autocomplete', 'off');

        // Create Dropdown Container
        var $dropdown = $('<div id="kyb-autocomplete-dropdown" class="kyb-dropdown"></div>');
        $('body').append($dropdown);

        // Inject Stylesheet (Linear / Shadcn monochromatic white & zinc system)
        if ($('#kyb-autocomplete-styles').length === 0) {
            var css = [
                '#kyb-autocomplete-dropdown {',
                '  position: absolute;',
                '  display: none;',
                '  background: #ffffff;',
                '  border: 1px solid #e4e4e7;',
                '  border-radius: 8px;',
                '  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);',
                '  max-height: 480px;',
                '  overflow-y: auto;',
                '  z-index: 100050;',
                '  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;',
                '  font-size: 12.5px;',
                '  color: #18181b;',
                '  min-width: 440px;',
                '  box-sizing: border-box;',
                '}',
                '.kyb-dropdown-header {',
                '  padding: 6px 12px;',
                '  background: #f4f4f5;',
                '  color: #52525b;',
                '  font-size: 11px;',
                '  font-weight: 600;',
                '  text-transform: uppercase;',
                '  letter-spacing: 0.04em;',
                '  border-bottom: 1px solid #e4e4e7;',
                '  display: flex;',
                '  justify-content: space-between;',
                '  align-items: center;',
                '  position: sticky;',
                '  top: 0;',
                '  z-index: 2;',
                '}',
                '.kyb-dropdown-item {',
                '  padding: 8px 12px;',
                '  cursor: pointer;',
                '  border-bottom: 1px solid #f4f4f5;',
                '  transition: background-color 0.12s ease;',
                '  display: flex;',
                '  justify-content: space-between;',
                '  align-items: center;',
                '}',
                '.kyb-dropdown-item:last-child {',
                '  border-bottom: none;',
                '}',
                '.kyb-dropdown-item:hover, .kyb-dropdown-item.active {',
                '  background-color: #f4f4f5;',
                '}',
                '.kyb-item-main {',
                '  display: flex;',
                '  flex-direction: column;',
                '  gap: 1.5px;',
                '  flex: 1;',
                '  margin-right: 12px;',
                '}',
                '.kyb-item-name {',
                '  font-weight: 600;',
                '  color: #18181b;',
                '  font-size: 12.5px;',
                '}',
                '.kyb-item-sub {',
                '  font-size: 11px;',
                '  color: #71717a;',
                '  font-weight: 400;',
                '  font-feature-settings: "tnum";',
                '}',
                '.kyb-pill {',
                '  display: inline-flex;',
                '  align-items: center;',
                '  padding: 2px 7px;',
                '  border-radius: 9999px;',
                '  font-size: 10px;',
                '  font-weight: 600;',
                '  white-space: nowrap;',
                '}',
                '.kyb-pill-active {',
                '  background: #ecfdf5;',
                '  color: #065f46;',
                '  border: 1px solid #a7f3d0;',
                '}',
                '.kyb-pill-radie {',
                '  background: #fef2f2;',
                '  color: #991b1b;',
                '  border: 1px solid #fecaca;',
                '}',
                '.kyb-dropdown-empty {',
                '  padding: 12px 14px;',
                '  color: #71717a;',
                '  font-size: 12px;',
                '  text-align: center;',
                '}',
                '.kyb-dropdown-footer {',
                '  padding: 5px 12px;',
                '  background: #fafafa;',
                '  border-top: 1px solid #e4e4e7;',
                '  color: #a1a1aa;',
                '  font-size: 10px;',
                '  text-align: right;',
                '  position: sticky;',
                '  bottom: 0;',
                '  z-index: 2;',
                '}',
                '.kyb-input-loading {',
                '  background-image: url("' + (cfg.workingGif || '/theme/common/working.gif') + '") !important;',
                '  background-position: right 8px center !important;',
                '  background-repeat: no-repeat !important;',
                '}'
            ].join('\n');
            $('head').append('<style id="kyb-autocomplete-styles">' + css + '</style>');
        }

        // Cache for query results to avoid redundant network / billing hits
        var queryCache = {};
        var currentXhr = null;
        var debounceTimer = null;
        var activeIndex = -1;
        var currentItems = [];
        var isSelecting = false;

        function positionDropdown() {
            var offset = $nameInput.offset();
            if (!offset) return;
            var height = $nameInput.outerHeight();
            var width = Math.max($nameInput.outerWidth(), 450);

            $dropdown.css({
                top: (offset.top + height + 3) + 'px',
                left: offset.left + 'px',
                width: width + 'px'
            });
        }

        function hideDropdown() {
            $dropdown.hide().empty();
            activeIndex = -1;
            currentItems = [];
        }

        function renderCandidates(candidates, term) {
            if (isSelecting) {
                hideDropdown();
                return;
            }

            // Strictly exclude Personnes Physiques (Loi 18-07 / Loi 25-11 / ADR #118)
            var clean = [];
            for (var i = 0; i < candidates.length; i++) {
                var c = candidates[i];
                var forme = (c.forme_juridique || '').toUpperCase();
                var rc = (c.rc_number || '').toUpperCase();
                var entityType = (c.entity_type || '').toUpperCase();

                if (entityType === 'PP') continue;
                if (forme.indexOf('PHYSIQUE') !== -1 || forme === 'PP') continue;
                if (/^[0-9]{1,2}[ACD]/.test(rc)) continue;

                clean.push(c);
            }

            currentItems = clean;
            activeIndex = -1;
            $dropdown.empty();

            if (clean.length === 0) {
                $dropdown.append(
                    '<div class="kyb-dropdown-empty">' +
                    (labels.noResults || 'Aucune entreprise trouvée') +
                    '</div>'
                );
                positionDropdown();
                $dropdown.show();
                return;
            }

            var $hdr = $(
                '<div class="kyb-dropdown-header">' +
                '<span>' + (labels.header || 'Référentiel National CNRC (Personnes Morales)') + '</span>' +
                '<span>' + clean.length + ' résultat(s)</span>' +
                '</div>'
            );
            $dropdown.append($hdr);

            for (var j = 0; j < clean.length; j++) {
                var item = clean[j];
                var isRad = (item.statut_cnrc || item.statut || '').toUpperCase().indexOf('RADI') !== -1;
                var pillClass = isRad ? 'kyb-pill-radie' : 'kyb-pill-active';
                var pillText = isRad ? (labels.radiated || 'RADIÉ') : (labels.active || 'ACTIF');

                var subtitleParts = [];
                if (item.rc_number_official || item.rc_number) {
                    subtitleParts.push('RC: ' + (item.rc_number_official || item.rc_number));
                }
                if (item.wilaya) {
                    subtitleParts.push(item.wilaya);
                }
                if (item.forme_juridique) {
                    subtitleParts.push(item.forme_juridique);
                }

                var $item = $(
                    '<div class="kyb-dropdown-item" data-index="' + j + '">' +
                    '  <div class="kyb-item-main">' +
                    '    <span class="kyb-item-name">' + $('<div>').text(item.raison_sociale).html() + '</span>' +
                    '    <span class="kyb-item-sub">' + $('<div>').text(subtitleParts.join(' · ')).html() + '</span>' +
                    '  </div>' +
                    '  <span class="kyb-pill ' + pillClass + '">' + pillText + '</span>' +
                    '</div>'
                );

                $dropdown.append($item);
            }

            var $ftr = $(
                '<div class="kyb-dropdown-footer">' +
                '<span class="fa fa-shield" style="color: #10b981; margin-right: 4px;"></span> ' +
                'KYB Algérie · Vérifié depuis les portails publics' +
                '</div>'
            );
            $dropdown.append($ftr);

            positionDropdown();
            $dropdown.show();
        }

        function triggerCandidatesSearch(term) {
            if (isSelecting) return;

            term = term.trim();
            if (term.length < 3) {
                hideDropdown();
                return;
            }

            if (queryCache[term.toLowerCase()]) {
                renderCandidates(queryCache[term.toLowerCase()], term);
                return;
            }

            if (currentXhr) {
                currentXhr.abort();
            }

            $nameInput.addClass('kyb-input-loading');

            currentXhr = $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    token: token,
                    mode: 'candidates',
                    q: term,
                    limit: 10
                },
                success: function(res) {
                    $nameInput.removeClass('kyb-input-loading');
                    currentXhr = null;
                    if (isSelecting) return;
                    if (res && res.success && res.candidates) {
                        queryCache[term.toLowerCase()] = res.candidates;
                        renderCandidates(res.candidates, term);
                    } else {
                        renderCandidates([], term);
                    }
                },
                error: function(xhr, status) {
                    if (status !== 'abort') {
                        $nameInput.removeClass('kyb-input-loading');
                        currentXhr = null;
                        hideDropdown();
                    }
                }
            });
        }

        // Full Profile Auto-Population by Identifier
        function triggerFullEnrichment(identifier, $sourceField) {
            if (!identifier || identifier.length < 5) {
                isSelecting = false;
                return;
            }

            if ($sourceField) {
                $sourceField.addClass('kyb-input-loading');
            }

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    token: token,
                    mode: 'resolve',
                    identifier: identifier
                },
                success: function(res) {
                    if ($sourceField) {
                        $sourceField.removeClass('kyb-input-loading');
                    }
                    if (res && res.success && res.data) {
                        applyEntityData(res.data);
                    }
                    isSelecting = false;
                },
                error: function() {
                    if ($sourceField) {
                        $sourceField.removeClass('kyb-input-loading');
                    }
                    isSelecting = false;
                }
            });
        }

        function applyEntityData(d) {
            if (!d) return;

            // 1. Nom / Raison Sociale
            if (d.raison_sociale) {
                $nameInput.val(d.raison_sociale);
            }

            // 2. Nom d'usage / Sigle
            if (d.sigle) {
                var $alias = $('input[name="name_alias"], #name_alias_input');
                if ($alias.length > 0) {
                    $alias.val(d.sigle);
                }
            }

            // 3. Identifiants Légaux (Décret 05-468)
            if (d.nif && $nifInput.length > 0) {
                $nifInput.val(d.nif);
            }
            if ((d.rc_official || d.rc) && $rcInput.length > 0) {
                $rcInput.val(d.rc_official || d.rc);
            }
            if (d.article_imposition) {
                var $ai = $('input[name="idprof2"]');
                if ($ai.length > 0) {
                    $ai.val(d.article_imposition);
                }
            }
            if (d.nis) {
                var $nis = $('input[name="idprof4"]');
                if ($nis.length > 0) {
                    $nis.val(d.nis);
                }
            }

            // 4. Adresse & Wilaya
            if (d.address) {
                var $addr = $('textarea[name="address"], textarea[name="adresse"]');
                if ($addr.length > 0) {
                    $addr.val(d.address);
                }
            }
            if (d.wilaya) {
                var $town = $('input[name="town"], #town');
                if ($town.length > 0) {
                    $town.val(d.wilaya);
                }
            }

            // 5. Capital Social (DA)
            if (d.capital_social) {
                var capClean = String(d.capital_social).replace(/[^0-9]/g, '');
                var $cap = $('input[name="capital"], #capital');
                if ($cap.length > 0 && capClean) {
                    $cap.val(capClean);
                }
            }

            // 6. Activité Principale (NAE/APE)
            if (d.nae_codes && d.nae_codes.length > 0) {
                var $ape = $('input[name="ape"], #ape');
                if ($ape.length > 0) {
                    $ape.val(d.nae_codes[0]);
                }
            }

            // 7. Forme Juridique
            if (d.forme_juridique) {
                var targetForme = d.forme_juridique.toUpperCase();
                $('select[name*="forme_juridique"] option').each(function() {
                    if ($(this).text().toUpperCase().indexOf(targetForme) !== -1) {
                        $(this).prop('selected', true).trigger('change');
                        return false;
                    }
                });
            }

            // User notification toast
            if (typeof $.jnotify === 'function') {
                $.jnotify(labels.autofillSuccess || 'Données vérifiées par KYB depuis les portails publics', 'info');
            }
        }

        // Event: Typing in Name input
        $nameInput.on('input', function() {
            if (isSelecting) return;
            var val = $(this).val();
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                triggerCandidatesSearch(val);
            }, 250);
        });

        // Event: Keyboard navigation in dropdown
        $nameInput.on('keydown', function(e) {
            if (!$dropdown.is(':visible') || currentItems.length === 0) {
                return;
            }

            if (e.keyCode === 40) { // Arrow Down
                e.preventDefault();
                activeIndex++;
                if (activeIndex >= currentItems.length) activeIndex = 0;
                updateActiveItem();
            } else if (e.keyCode === 38) { // Arrow Up
                e.preventDefault();
                activeIndex--;
                if (activeIndex < 0) activeIndex = currentItems.length - 1;
                updateActiveItem();
            } else if (e.keyCode === 13) { // Enter
                if (activeIndex >= 0 && activeIndex < currentItems.length) {
                    e.preventDefault();
                    selectCandidate(currentItems[activeIndex]);
                }
            } else if (e.keyCode === 27) { // Escape
                hideDropdown();
            }
        });

        function updateActiveItem() {
            $dropdown.find('.kyb-dropdown-item').removeClass('active');
            if (activeIndex >= 0) {
                var $active = $dropdown.find('.kyb-dropdown-item[data-index="' + activeIndex + '"]');
                $active.addClass('active');
                if ($active.length > 0) {
                    var el = $active[0];
                    if (el.scrollIntoView) {
                        el.scrollIntoView({ block: 'nearest' });
                    }
                }
            }
        }

        function selectCandidate(cand) {
            if (!cand) return;

            isSelecting = true;
            clearTimeout(debounceTimer);
            if (currentXhr) {
                currentXhr.abort();
                currentXhr = null;
            }

            hideDropdown();

            $nameInput.val(cand.raison_sociale);
            if (cand.rc_number_official || cand.rc_number) {
                $rcInput.val(cand.rc_number_official || cand.rc_number);
            }
            if (cand.nif) {
                $nifInput.val(cand.nif);
            }

            // Trigger full resolve using NIF or RC
            var lookupId = cand.nif || cand.rc_number || cand.rc_number_official;
            triggerFullEnrichment(lookupId, $nameInput);
        }

        // Click on dropdown item
        $dropdown.on('click', '.kyb-dropdown-item', function(e) {
            e.stopPropagation();
            var idx = parseInt($(this).attr('data-index'), 10);
            if (!isNaN(idx) && currentItems[idx]) {
                selectCandidate(currentItems[idx]);
            }
        });

        // Click outside closes dropdown
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#kyb-autocomplete-dropdown, input[name="name"], input[name="nom"], #name').length) {
                hideDropdown();
            }
        });

        // Reposition on window resize or scroll
        $(window).on('resize scroll', function() {
            if ($dropdown.is(':visible')) {
                positionDropdown();
            }
        });

        // Identifiers direct blur triggers
        if ($nifInput.length > 0) {
            $nifInput.on('blur', function() {
                if (isSelecting) return;
                var val = $(this).val().trim();
                if (val.length >= 9) {
                    triggerFullEnrichment(val, $nifInput);
                }
            });
        }

        if ($rcInput.length > 0) {
            $rcInput.on('blur', function() {
                if (isSelecting) return;
                var val = $(this).val().trim();
                if (val.length >= 6) {
                    triggerFullEnrichment(val, $rcInput);
                }
            });
        }
    });
})(typeof jQuery !== 'undefined' ? jQuery : (typeof $ !== 'undefined' ? $ : undefined));
