# NewsPress Revisions

Revisions plugin provides an alternative method of displaying WordPress post revisions from the existing core WordPress system. This differs in a number of ways. The plugin leverages Gutenberg to render side-by-side posts with diffs as you would expect to see them in the editor view, removing the clutter of Gutenberg tags and HTML in the revision views. Soon, this will also include a full redesign and change of the UX to fit a more modern need and to move away from the outdated WordPress revision experience.

<!-- TOC -->

- [NewsPress Revisions](#newspress-revisions)
    - [Project Structure](#project-structure)
    - [JS Hooks](#js-hooks)
        - [Filters](#filters)
            - [newspress.revisions.hideCoreTitle](#newspressrevisionshidecoretitle)
            - [newspress.revisions.getContentBodyRegex](#newspressrevisionsgetcontentbodyregex)
            - [newspress.revisions.contentSources](#newspressrevisionscontentsources)
            - [newspress.revisions.blocksNoReplace](#newspressrevisionsblocksnoreplace)
            - [newspress.revisions.blocksNoSupport](#newspressrevisionsblocksnosupport)
            - [newspress.revisions.filterOptions](#newspressrevisionsfilteroptions)
    - [PHP Hooks](#php-hooks)
        - [Filters](#filters)
            - [newspress.revisions.metaExclusions](#newspressrevisionsmetaexclusions)
            - [ncu_newspress_revisions_should_store_potentially_empty_post](#ncu_newspress_revisions_should_store_potentially_empty_post)
            - [ncu_newspress_revisions_revision_is_empty](#ncu_newspress_revisions_revision_is_empty)
            - [newspress.revisions.restItemMeta](#newspressrevisionsrestitemmeta)
        - [Actions](#actions)
            - [‌newspress.revisions.postStatusChange](#%E2%80%8Cnewspressrevisionspoststatuschange)
            - [newspress.revisions.restRevisionItem](#newspressrevisionsrestrevisionitem)
            - [newspress_revisions_handle_autosaves](#newspress_revisions_handle_autosaves)
    - [Store Methods](#store-methods)
        - [Actions](#actions)
            - [setBlockMetaFields](#setblockmetafields)
        - [Selectors](#selectors)
            - [isInitialised](#isinitialised)
            - [getPostId](#getpostid)
            - [getPost](#getpost)
            - [getRevisionOrder](#getrevisionorder)
            - [getRevisions](#getrevisions)
            - [getRevision](#getrevision)
            - [getCurrentRevision](#getcurrentrevision)
            - [getCurrentRevisionObject](#getcurrentrevisionobject)
            - [getBlocks __depreciated__](#getblocks-__depreciated__)
            - [getCachedBlocks](#getcachedblocks)
            - [isParsing](#isparsing)
            - [canRestoreRevision](#canrestorerevision)
            - [isPostLocked](#ispostlocked)
            - [readonly](#readonly)
            - [isRestoring](#isrestoring)
            - [isPaginating](#ispaginating)
            - [getCurrentPage](#getcurrentpage)
            - [getMaxPages](#getmaxpages)
            - [getEditLink](#geteditlink)
            - [getPostFromBlockId](#getpostfromblockid)
            - [getBlockMetaFields](#getblockmetafields)
            - [isOpen](#isopen)
            - [getDraftCount](#getdraftcount)
    - [Configuration Notes](#configuration-notes)
        - [Preparing your block](#preparing-your-block)
        - [Conditional Rendering For Revisions](#conditional-rendering-for-revisions)
        - [Working with Block Meta and Revisions](#working-with-block-meta-and-revisions)
        - [Setting up custom diffing in a block](#setting-up-custom-diffing-in-a-block)
        - [Unsupported blocks](#unsupported-blocks)
    - [Contribution](#contribution)
        - [Setup](#setup)
        - [Standards](#standards)
        - [Requirements](#requirements)
        - [Testing](#testing)
        - [Releasing](#releasing)
        - [Scripts](#scripts)

<!-- /TOC -->

## Project Structure

```
├── dist                        # Built files
├── inc                         # PHP Files
│   └── views                   # Admin Views
├── src                         # scripts/styles source
│   ├── editor                  # editor scripts
│   │   └── styles              # editor styles
│   ├── entrypoints             # webpack entry points
│   ├── revisions-view          # Revisions view
│   │   ├── blocks              # Revisions specific blocks
│   │   ├── components          # Components
│   │   ├── containers          # Containers, components that manage state
│   │   ├── parser              # Revisions parsing
│   │   │   └── differ          # Diffing library
│   │   ├── store               # Revisions store
│   │   ├── styles              # Styles
│   │   ├── subscriptions       # Revisions store subscriptions
│   │   └── utils               # Utilities
│   └── static                  # Static files
├── tests                       # PHP Tests
└── webpack                     # Webpack Config
```

## JS Hooks

### Filters

#### `newspress.revisions.hideCoreTitle`

Allows for the core title to be hidden from the diff. This is especially useful when using your own title solution, such as [`ncu-newspress-multi-title-support`](https://github.com/newscorp-ghfb/ncu-newspress-multi-title-support).

**Usage:**

```js
addFilter('newspress.revisions.hideCoreTitle', 'namespace/my-plugin', () => {
  return true;
});
```
---

#### `newspress.revisions.getContentBodyRegex`

As part of the revision parsing and diffing process, raw content is used to create the parse. Due to raw content being a requirement, if content is wrapped in specific html or blocks, a regex string is needed to extract the content from the wrapper to then be used as the diffed content.

This regex is also used to insert the diffed content back into the raw content.

**Usage:**

```js
const REGEX_CONTENT_BODY = /<!-- wp:the-times\/(?:[\w-]+)?template(?:[\w-]+)? -->\s<section[^>]*><div[^>]*>([\s\S]+?)<\/div><\/section>\s<!-- \/wp:the-times\/(?:[\w-]+)?template(?:[\w-]+)? -->/;

addFilter('newspress.revisions.getContentBodyRegex', 'namespace/my-plugin', () => {
  return REGEX_CONTENT_BODY;
});
```
---

#### `newspress.revisions.contentSources`
Content sources allows for definition of a number of blocks that are used as a wrapper for your content. When used the column builder in the revisions will look for a block provided by the filter to extract and later re-implement the content from.

So for example, if your content is wrapped or nested in the block `my-project/wrapper`, you can define this to only use its inner content as part of the diffing parse.

**Usage:**

```js

addFilter(‘newspress.revisions.contentSources’, 'namespace/my-plugin', () => {
  return [
    'the-times/template',
    'the-times/default-template',
    'the-times/sport-template-default',
    'newspress/related-content',
    'newspress/no-revisions',
  ];
});

```

---

#### `newspress.revisions.blocksNoReplace`
Defines which blocks changes should not get wrapped in `<ins>` and `<del>` tags.

For example you have a block such as the `shortcode` block which outputs content in a textarea, you can stop the tags being inserted as they'll just be diaplyed as part of the textarea value rather than rendering/highlighting the change.

**Usage:**

```js
addFilter(‘newspress.revisions.blocksNoReplace’, 'namespace/my-plugin', (defaultBlocks = []) => {
  return [
    ...defaultBlocks,
    'shortcode',
  ];
});
```

---

#### `newspress.revisions.blocksNoSupport`

Defines a list of blocks that cannot be compared by the differ, whether by design or due to the architectural nature of your block.

This will cause defined blocks to be wrapped in the `newspress/revisions-no-support` block to differentiate and emphasis this to the end user. This is done to prevent confusion and allow for understanding that it may not be supported.

Adding your block to this list will also add it to the default list used in [`newspress.revisions.blocksNoReplace`](#newspressrevisionsblocksnoreplace) to ensure rendering of diffs will not be attempted.

**Usage:**
```js
addFilter('newspress.revisions.blocksNoSupport', 'namespace/my-plugin', (defaultBlocks = []) => {
  return [
    ...defaultBlocks,
    'wsj/bylines',
  ];
});

```

---

#### `newspress.revisions.filterOptions`

Defines a list of status that you can filter timeline items by.

This filter can be used to combine statuses for example if you wanted to show both published and updates at the same time.

**Usage:**

```js
addFilter('newspress.revisions.filterOptions', 'newspress.dj.revisions.adapter', (options) =>
  options.reduce((prev, curr) => {
    if (curr.value === 'update') {
      return prev;
    }

    if (curr.value === 'publish') {
      prev.push({ label: 'Published / Updated', value: ['publish', 'update'] });
      return prev;
    }

    prev.push(curr);

    return prev;
  }, []),
);
```

---

## PHP Hooks

### Filters

#### `newspress.revisions.metaExclusions`

Meta to be excluded from the current post before moving it to the given revision.

**Constant:** `NewsPress\Revisions\Meta::META_EXCLUSIONS_FILTER`

**Arguments:**

- `$removals` (`Array`): List of meta keys defining which meta will be removed.
- `$post_id` (`Int`): The ID of the post being processed.

#### `ncu_newspress_revisions_should_store_potentially_empty_post`

Should revisions force WordPress to potentially store an empty post

**Arguments**

- `$should_store` (`Bool`): current should store value

#### `ncu_newspress_revisions_revision_is_empty`

Return own definition of a revision being empty.

**Arguments**

- `$maybe_empty` (`Bool`): current should store value
- `$post_arr` (`Array`): Array of post data

---

#### `newspress.revisions.restItemMeta`

Meta for each revision that will then be used in the revision REST API response.

**Constant:** `NewsPress\Revisions\Meta::REST_ITEM_META_FILTER`

**Arguments:**

- `$revision_meta` (`Array`): List of available meta to be passed to the revision item.
- `$revision_id` (`Int`): ID of the revision.

**Usage:**

```php
add_filter( 'newspress.revisions.restItemMeta', function( $revision_meta, $revision_id ) {
	$revision_meta['example'] = true;

	return $revision_meta;
} );
```

---

### Actions

#### `‌newspress.revisions.postStatusChange`

When a post status is changed, revisions will add it's own meta indicating a transition has been made and will attach meta stating what the status is. This action is called once meta has been set.

**Constant:** `NewsPress\Revisions\Meta::POST_STATUS_CHANGE_ACTION`

**Arguments:**

- `$new_status` (`String`): New status to be transitioned too.
- `$old_status` (`String`): Old status before transition.
- `$post` (`Object`): Post being saved.

**Usage:**

```php
add_filter( 'newspress.revisions.postStatusChange', function( $new_status, $old_status, $post ) {
	if ( 'publish' === $new_status ) {
		update_metadata( 'post', $post->ID, 'my_additional_meta', 'updated' );
	}
} );
```

---

#### `newspress.revisions.restRevisionItem`

Called for each revision item after it is processed for meta changes and additions.

**Constant:** `NewsPress\Revisions\Meta::REST_REVISION_ITEM_ACTION`

**Arguments:**

- `$revision` (`Object`): Revision item that has been processed before returning to the REST response.

**Usage:**

```php
add_filter( 'newspress.revisions.restRevisionItem', function( $revision ) {
	// Do something with the revision item.
} )
```

---

#### `newspress_revisions_handle_autosaves`

Sets whether or not autosaves are to be handled by revisions.

**Arguments:**

- `$handle` (`Boolean`): Whether to enable/disable autosaves in revisions.

**Usage:**

```php
// Disable autosaves.
add_filter( 'newspress_revisions_handle_autosaves', '__return_false' );
```


---

## Store Methods

Revisions store reside on the `newspress/revisions` namespace so relevant methods can be accessible from `dispatch` and `select`.

### Actions

#### `setBlockMetaFields`

Defines the a block meta fields/keys that can be used for comparison. See the [Working with Block Meta and Revisions section](#working-with-block-meta-and-revisions) for more information on meta implementation.

**Parameters**

- `blockName` (`String`): The namespaced block name.
- `metaKeys` (`Array`): The meta keys which will be compared against each other.

**Example Usage**

```js
wp.data
  .dispatch('newspress/revisions')
  ?.setBlockMetaFields('newspress/multi-titles', ['newspress_multi_title_support']);
```

**Returns** `void`

### Selectors

#### `isInitialised`

Return initialising status from state.

**Returns** `boolean`

#### `getPostId`

Return current post id from state.

**Returns** `number`

#### `getPost`

Return post object

**Parameters**

- mostRecent (`boolean`) - should return the latest post

**Returns** `object`

#### `getRevisionOrder`

Get an array of id's that represent revisions in order.

**Returns** `Array`

#### `getRevisions`

Get revisions

**Returns** `object`

#### `getRevision`

Get revision object by ID.

**Parameters**

- id (`number`) - Revision ID

**Returns** `object`

#### `getCurrentRevision`

Get current revision ID

**Returns** `number`

#### `getCurrentRevisionObject`

Get current revision object

**Returns** `object`

#### `getBlocks` __depreciated__

Get revisions blocks

**Parameters**

- id (`Number`) - Revision ID

**Returns** `Array`

#### `getCachedBlocks`

Get revisions blocks

**Parameters**

- id (`Number`) - Revision ID

**Returns** `Array`

#### `isParsing`

Returns if the blocks for the passed revisions id is parsing

**Parameters**

- id (`Number`) - Revision ID

**Returns** `boolean`

#### `canRestoreRevision`

Returns if the user has permission to restore a revision

**Returns** `boolean`

#### `isPostLocked`

Returns if the current post is locked.

**Returns** `boolean`

#### `readonly`

Returns if the revisions is open in readonly mode.

**Returns** `boolean`

#### `isRestoring`

Is revisions currently restoring a revision

**Returns** `boolean`

#### `isPaginating`

Is currently paginating

**Returns** `boolean`

#### `getCurrentPage`

Return the current page of revisions

**Returns** `number`

#### `getMaxPages`

Returns the max number of pages that can be paginated.

**Returns** `number`

#### `getEditLink`

Get post edit link

**Returns** `string`

#### `getPostFromBlockId`

Get post attribute from block id. Checks whether the block is a revision or main post

**Parameters**

- blockId (`String`) - block id
- key (`String`) - post attribute name

**Returns** `mixed`

#### `getBlockMetaFields`

Get the fields for the given block that are to be used for meta comparison during diffing.

**Parameters:**

- `blockName` (`String`): Name of the block, including namespace, to retrieve the meta fields for.

#### `isOpen`

Returns true as if we are on this page revisions is open.

**Returns** `boolean`

#### `getDraftCount`

Get revisions draft count.

**Returns** `number`

---

## Configuration Notes

After install revisions should work as-is. There are a number of available hooks across both [Javascript](#javascript-hooks) and [PHP](#php-hooks) to provide extra extensibility when developing your plugin, theme or site. If the plugin is running on a DowJones publication, there is a adapter plugin which provides extra features such as comparing meta from standard meta fields used on the publication and also being able to distinguish between a local save and publishes on articles. This can be found here [DJ Revision Adapter](https://github.com/newscorp-ghfb/ncu-newspress-dj-revisions-adapter)

### Preparing your block

As NewsPress Revisions is rendered with Gutenberg, it relies on the output of the [`edit` method](https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit) from your `registerBlockType` call to be able to make the comparison.

These hooks can live in one of two places;

1. Within your plugin.
2. your theme.

As WordPress filters and actions are being used for customisation, it doesn't matter where in your plugin or theme where these are defined.

If your block does not have any output from the `edit` method, blocks will still render, but will not be compared. For these instances, see [Unsupported blocks](#unsupported-blocks).

### Conditional Rendering For Revisions

Sometimes, within your block, there are instances where you would like to alter the output or some other aspect of your block based on whether revisions are open.

For instance, you might want to ignore any alignment, wrapping or styling that your block is set to while revisions are open. This could also apply to altering attributes, props or other data passed to the block.

When these instances occur, it's best to check that the Revisions view is open using the [`isOpen` store selector](#isopen).

For example, if you want to set a specific className when Revisions are open you can do the following;

```js
const revisionsOpen = select('newspress/revisions').isOpen();

const classNames = classnames({
	'my-plugin__title',
	'my-plugin__title--large',
	'my-plugin__title--is-revisions': revisionsOpen,
});
```

### Working with Block Meta and Revisions

When working with revisions, your block may have specific needs when in revisions mode. For example, your block may read data from WordPress meta data rather than attributes. As a result you may find your block not able to show different values between the current post and selected revision.

To ensure you can have the right data available to you, you will need to use the correct variation of data saved by the block. To get correct data available for the block, you can utilise the [`getPostFromBlockAttributes`](#getpostfromblockattributes) selector to retrieve that data.

Revisions will assign a custom attribute property of `__revisions` _only_ (and at no other time) when revisions is open. This holds identifiers for revisions to know which block it is being rendered as, a revision, or the current post.

For example, the method used by [`newscorp-ghfb/multi-title-support`](https://github.com/newscorp-ghfb/ncu-newspress-multi-title-support) is to get the post meta from within `withSelect`.

```js
const getEditedPostAttribute = (select, key, blockId) => {
  const { getPostFromBlockId = false } = select('newspress/revisions') || {};

  if (getPostFromBlockId) {
    return getPostFromBlockId(blockId, key);
  }

  return select('core/editor').getEditedPostAttribute(key);
};

const applyWithSelect = withSelect((select, ownProps) => {
  return {
    postMeta: getEditedPostAttribute(select, 'meta', ownProps.clientId),
    x,
  };
});
```

When comparing blocks that are powered by meta values rather than, or in conjunction with meta, you will need to define the meta values which are being compared.

To tackle this you can define the meta values for the block which can be go through comparison. These will be compared with [strict equality](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Equality_comparisons_and_sameness) checks. Meta is defined using the [`setBlockMetaFields`](#setblockmetafields) action, by passing the block name (including namespace) and an array of meta keys.

```js
wp.data
  .dispatch('newspress/revisions')
  ?.setBlockMetaFields('newspress/multi-titles', ['newspress_multi_title_support']);
```

_Note: It's recommended you call `setBlockMetaFields` using the [Null Propagation Operator/Optional Chaining](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Optional_chaining) as shown above. This will allow you plugin to work without revisions being enabled, preventing any `property of null` errors._

### Setting up custom diffing in a block

When working with your block, you may have a usecase or issue where diffing isn't supported by default. This may mean a requirement to diff metadata, or event API result data. To cater for situations where you need custom diffing, you can use the [`withBlockRevision()`](https://github.com/newscorp-ghfb/newspress-utils/tree/main/src/hoc/withBlockRevision) Higher Order Component that is part of the [`newspress-utils` package](https://github.com/newscorp-ghfb/newspress-utils). Usage examples can be found in the [`withBlockRevision()` documentation](https://github.com/newscorp-ghfb/newspress-utils/tree/main/src/hoc/withBlockRevision).

### Unsupported blocks

There may be instances that make it impossible for your block to be compared, but still needs to be rendered in the side-by-side comparison.

Blocks with an overly complex structure, or not optimised for revisions, may not render correctly.

If your block does not have any output from the `edit` method, blocks will still render, but it is still advisable to add some necessary config.

For both of these instances you can add [`newspress.revisions.blocksNoSupport`](#newspressrevisionsblocksnosupport) to your plugin with your definition. This will take some necessary steps to ensure that your block can be rendered.

## Contribution

### Setup
Clone the repository into your `plugins` or `client-mu-plugins` directory.

```
git clone git@github.com:newscorp-ghfb/ncu-newspress-revisions.git && cd ncu-newspress-revisions
```

Install JS packages.

```
yarn
```

Build all assets - additional commands can be found on the [`bigbite/build-tools` repo.](https://github.com/bigbite/build-tools#commands)

```
yarn build:dev
```

Install PHP packages and create autoloader for the plugin.

```
composer update
```

### Standards

This plugin uses the following coding standards:

-   PHPCS: [WordPress Coding Standards](https://nidigitalsolutions.jira.com/wiki/spaces/NCP/pages/4392550441/Plugin+documentation+template# "#") and [WordPress VIP Coding Standards](https://nidigitalsolutions.jira.com/wiki/spaces/NCP/pages/4392550441/Plugin+documentation+template# "#")
-   ESLint: [WordPress Coding Standards](https://nidigitalsolutions.jira.com/wiki/spaces/NCP/pages/4392550441/Plugin+documentation+template# "#") and [WordPress VIP Coding Standards](https://nidigitalsolutions.jira.com/wiki/spaces/NCP/pages/4392550441/Plugin+documentation+template# "#")

### Requirements
-   PHP 8.0+
-   WordPress 5.7+
-   Node 16.20+

### Testing

The plugin includes tests for both PHP and JavaScript. The tests are run using [PHPUnit](https://phpunit.de/ "https://phpunit.de/") and [Jest](https://jestjs.io/ "https://jestjs.io/"). Code coverage is expected to be more than 90% for both PHP and JavaScript. The tests can be run using the following commands:

```sh
# Run JavaScript tests
yarn test:js

# Run cypress tests
yarn test:e2e
```

### Releasing

The plugin uses [Semantic Versioning](https://semver.org/ "https://semver.org/") for versioning. The version number is defined in the `package.json` file. The plugin is released using [GitHub Releases](https://docs.github.com/en/github/administering-a-repository/releasing-projects-on-github/about-releases). To create a new release, follow these steps:

1. Bump the version numbers in package.json, plugin.php
2. Update CHANGELOG.md with changes
3. Push the changes to the `master` branch, by merging the associated pull request
4. After Circle CI build has complete create a release tagging the `master-built` branch

### Scripts

```
# Run jest tests
yarn test:js

# build dev (unminified)
yarn build:dev

# build prod (minified)
yarn build:prod

# watch dev (unminified)
yarn watch:dev

# watch prod (minified)
yarn watch:prod

# lint styles
yarn lint:styles

# lint scripts
yarn lint:scripts

# start WP Cypress
yarn test:e2e:start

# run WP Cypress
yarn test:e2e

# open cypress ui
yarn test:e2e:gui

# Lint php
composer lint:php
```
