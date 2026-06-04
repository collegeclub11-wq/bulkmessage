// node-service/src/utils/helpers.js

function cleanPhoneNumber(number) {
  return number.replace(/[^0-9]/g, '');
}

function parseJSON(data, fallback = {}) {
  try {
    return JSON.parse(data) || fallback;
  } catch (e) {
    return fallback;
  }
}

module.exports = {
  cleanPhoneNumber,
  parseJSON
};
