const wpCypressPlugin = require('@bigbite/wp-cypress/lib/cypress-plugin');
const cucumber = require('cypress-cucumber-preprocessor').default;

module.exports = async (on, config) => {
  on('file:preprocessor', cucumber());
  return wpCypressPlugin(on, config);
};
