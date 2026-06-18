/**
 * Preloaded field presets registry
 *
 * To add a new preloaded list:
 *   1. Create a new file in this directory (e.g. my-list.js) that exports a default array of { value, label } objects.
 *   2. Import it below and add an entry to PRESETS.
 *
 * That's all — the builder palette and click handler read from this registry automatically.
 */

import countries  from './countries.js';
import industries from './industries.js';

/**
 * Each entry:
 *   label   — display name shown in the field palette button
 *   options — array of { value, label } objects used to populate the dropdown
 */
export const PRESETS = {
    countries: {
        label:   'Country List',
        options: countries,
    },
    industries: {
        label:   'Industry / Sector',
        options: industries,
    },
};
