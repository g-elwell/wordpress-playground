# Changelog

## 3.4.1
- Fix an issue with getCurrentRevisionObject not returning the revision and also getPost not returning the correct right hand side post.

## 3.4.0
- Update Node to version 16 ([CP-3592](https://dowjones.atlassian.net/browse/CP-3592)).
    - Update NPM to version `8`.
    - Update packages to latest stable versions supported by Node `16`.
    - Auto-linting scripts to adhere to updated eslint rules.
- Add `.node-version` file documenting the node version being used in CI for this repo ([CPNT-2100](https://nidigitalsolutions.jira.com/browse/CPNT-2100)).

## 3.3.2
- Add a try / catch in Revision component to ensure that view doesn't error out.

## 3.3.1
- Add catalog-info.yml file to add plugin on backstage catalog

## 3.3.0
- Update PHP version requirements and platform value in `composer.json` to reflect compatibility with PHP `8.0`, `8.1`, and `8.2` and update Composer dependencies ([CP-3591](https://dowjones.atlassian.net/browse/CP-3591)).
- This release is compatible with WordPress `6.3.1` ([CP-3590](https://dowjones.atlassian.net/browse/CP-3590))

## 3.2.2
- Adds selection state to compare previous revision dropdown and conditional class used by the dj revisions adapter.

## 3.2.1
- Fixes [#293](https://github.com/newscorp-ghfb/ncu-newspress-revisions/issues/293) "In 3.1, buttons appear when you click into a block". Targets correct css selector to fix this.

## 3.2.0
- Adds a shortcut to open revisions from within the editor (Primary + Alt + R)

## 3.1.1
- Fixes [#301](https://github.com/newscorp-ghfb/ncu-newspress-revisions/issues/301) "In PHP 8.2 and WP 6.2.x, method register_page() causes Deprecation exception". Added empty slug string to `add_submenu_page()` args to prevent this.

## 3.1.0
- Adds ability to compare any two previous revisions against each other, the right hand side post is controlled via a dropdown.
## 3.0.5
- Fix an issue when restoring a revision would store stale metadata on the newly created revision

## 3.0.4
- Fix default metadata comparison

## 3.0.3
- Fix timeline style issue introduced in 3.0.1

## 3.0.2
- Get metadata default for comparing metadata if it is not present

## 3.0.1
- Update prop-types validation with current way the plugin operates.

## 3.0.0
- Adds a `Single View` mode to allow reading of a revision as a single item rather than a split comparison.
- Adds improved handling and capabilities around diffing meta values and custom data in blocks using a newly introduced [`withBlockRevision()`](https://github.com/newscorp-ghfb/newspress-utils/tree/main/src/hoc/withBlockRevision) Higher order component.
- Adds compatibility with [NewsUK 'Unified Sidebars' plugin](https://github.com/newsuk/nuk-wp-unified-sidebars-menu).
- Fixes an issue where meta would not unserialise correctly if saved as an array.
- Fixes an issue where article restoration would not create a revision and instead overwrite the current version.
- Fixes an issue where revision titles and numbers would be inconsistent.

## 2.8.4
- Backported from `3.1.1`: Fixes [#301](https://github.com/newscorp-ghfb/ncu-newspress-revisions/issues/301) "In PHP 8.2 and WP 6.2.x, method register_page() causes Deprecation exception". Added empty slug string to `add_submenu_page()` args to prevent this.
- Backported from `3.3.0`: Update PHP version requirements and platform value in `composer.json` to reflect compatibility with PHP `8.0`, `8.1`, and `8.2` ([CP-3591](https://dowjones.atlassian.net/browse/CP-3591)).
- Backported from `3.3.0`: This release is compatible with WordPress `6.3.1` ([CP-3590](https://dowjones.atlassian.net/browse/CP-3590))

## 2.8.3
- Add `newspress_revisions_handle_autosaves` filter so that autosave integration can be disabled.

## 2.8.2
- Fix issue where post action wouldnt show on post types other than posts

## 2.8.1
- Fix issue with autosave meta endpoint being called numerous times when it should only get called once per autosave.

## 2.8.0
- Integrate autosaves.
  - New API endpoint (`revisions/v1/autosave/${autosave-post-id}`) for updating meta for autosave.
    - Updates `newspress_status` to `autosave` and sets `newspress_saved_by` to current user id.
  - Check if autosave exists for current user, if it does do not exclude it from revisions array, so that it can be rendered in the revisions view.
  - Set `newspress_status` to `reverted` when restoring autosave, allows us to display `Restored from Autosave` label in header/timeline.
  - Delete all autosaves after restoring.
  - Delete all autosaves where post modified date is older than current post modified date.
  - Add subscribe function which waits for an autosave to occur and then calls the custom endpoint with meta to be saved against the autosave.
  - Set header/timeline label to `Autosave` if `newspress_status` equals `autosave`.
  - Replace database autosave notice with custom notice, same content but it links to custom revisions view.
  - Replace local storage autosave notice with custom notice, same content however the `onClick` method will restore post meta that was saved to local storage.
  - Overwrite `wp.data.dispatch('core/editor').autosave` with custom function which is almost identical apart from the additional setting of meta data to local storage.
  - Use `reverseDiffTags` prop to reverse the diffing when displaying autosave so that the diffing makes sense, additions will now appear green as additions rather than deletions as they did previously.

## 2.7.3
- Fixed an issue where array metadata wasn't being stored correctly.

## 2.7.2
- Fixed an issue where first revision wouldnt have any meta attached
- Fixed an issue where meta is one revision out of date

## 2.7.1
- Set `newspress_saved_by` for post when transitioning from `future` to `publish` using the `newspress_saved_by` value from the scheduled revision.
  - This was required due to the CRON creating the revision and `get_current_user_id` returning `0`.

## 2.7.0
- Call `wp_update_post` when post is transitioned from `future` to `publish`, in order to trigger a revision to be created.
- Pass old status to `newspress.revisions.getPostStatus` filter.
- Pass `transitioned` property for revision headings.

## 2.6.1
- Fix issue with autosaves appaering in revisions view.

## 2.6.0
- Add ability to configure status filters externally via `newspress.revisions.filterOptions` filter.

## 2.5.1
- Add closing slash to line break tags and add them to the element tree to show diff when paragraphs are split.
- Display tick against timeline items if status is `publish` or `update`, was previously displayed if status was `saved`.

## 2.5.0
- Add `newspress_count` meta to post/revisions to store the count for the current revision status at the time.
- Add new `newspresss_count` to list of fields to ignore when reverting.
- Change `get_revisions_count` so that it gets the count and no longer stores it against `revision_{STATUS}_count` field.
- `saveData.reverted_from` is now an array containing `id`, `newspress_status` , `newspress_count`, `save_transitioned`, rather than just the post id.
- Client side code has changed so that the status counts now come from `newspress_count`, rather than us calculating them.
- Added `revertedFrom`  to `getSatatusText` so that the reverted text can be ran though any filter(s).
- Add condition for `future` status to `getSatatusText`.
- Add condition for `update` status to `getSatatusText`.
- If `publish` and post status has not transitioned set `newspress_status` to `update`.
- Reverted labels will now display which reivion was used ie "Reverted from Draft 3" rather than just "Reverted".

## 2.4.4
- Chore: Replaces deprecated `IconButton` with `Button`

## 2.4.3
- Fix: Ensure compatibility with WordPress 6.1.*.
- Fix: Use 'modified' property for date separation in the timeline rather than 'date' to print correct values.
- Chore: Change event status text to Published / Updated for publish event types.

## 2.4.2
- Chore: Only display previous years in year date separator.
- Fix: use correct filter name newspress_revisions_counts
- Fix incorrect date showing in timeline items.

## 2.4.1
- Pass `force_update` as `true` when fetching counts are reverting.

## 2.4.0
- Adds counts to all revision statuses (`publish`, `draft` & `saved` (saved is when an update is made to a published post)).
- Add newspress_revisions_counts so statuses to count can be configured externally.
- Add newspress_revisions_count_status so valid post status's can be configured externally.
- Make passing of status counts more generic so that additional statuses can be added via above two filters and be displayed client side by using newspress.revisions.getStatusText js filter.

## Version 2.3.7
- Adds ability to filter timeline items by event status.

## Version 2.3.6
- Adds year separation headings for revision timeline items.

## Verion 2.3.5
- Reset the last index so regex is no longer null the second time ran in the loop.

## Version 2.3.4
- Fixed issue with hardcoded DB name in query.
- If only one revision, display it rather than saying no revisions.

## Version 2.3.3
- Fixed incorrect computation of revision author introduced in 2.3.1

## Version 2.3.2
- Fixed getEditedPostAttribute stub to handle post content correctly

## Version 2.3.1
- Fixed undefined index error notice, handled null value of $author_id

## Version 2.3.0
- Update PHP requirements. Require a PHP version >=7.4 or ~8.0.0
- Ensure compatibility with both PHP 7.4 and 8.0.
- fix: Error related to is_wp_error() function
- fix: Access to an undefined property error
## Version 2.2.6
- Fixed `getEntityRecord` stub so that it checks if the id is either the post id or the current revision id, if it
is it will return either the post or revision entity record.
  - Blocks which store data in post meta will now render in the revisions view.

## Version 2.2.5
- Add `rest_get_route_for_post_type_items` so plugin works on WordPress versions earlier than `5.9`.

## Version 2.2.4
- Correct toolbar icon spacing due to link

## Version 2.2.3
- ci: update ci to support php 8

## Version 2.2.2
- fix: update getEntityRecord to allow for fetching entities other than itself

## Version 2.2.1
- fix: serverside registered blocks render correctly in revisions
- fix: self closing blocks at the end of the content would be removed

## Version 2.2.0

- feat: add support for comparing non-single meta values
- fix: invalid draft count
- fix: check if rest init has run if not intialise the fields

## Version 2.1.1

- Fix an issue where rest fields aren't initted on initial post load.

## Version 2.1.0

- Adds better support for custom post types
- Fixes an issue with meta saving where we naively assume they are just a single value

## Version 2.0.2

- Overwrite the default `wp_insert_post_empty_content` filter value to prevent other plugins from preventing revisions being stored
- Add a filter in to disable this feature
- Add a filter to modify what revisions should consider as an empty revision

## Version 2.0.1

- pass post type to fetch_revisions as we naively assumed it was post

## Version 2.0.0

- Move revisions out of the editor onto its own page.
- Fixes numerous attribution and status issues caused by revisions not been created
- Add filter to compare meta fields to create a new revision
