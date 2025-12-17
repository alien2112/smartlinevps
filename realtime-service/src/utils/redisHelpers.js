/**
 * Redis Helper Utilities
 * Fixes compatibility issues with ioredis multi/pipeline hset
 */

/**
 * Convert object to flat hset arguments
 * Fixes: ERR wrong number of arguments for 'hset' command
 *
 * @param {Object} obj - Object with field-value pairs
 * @returns {Array} - Flat array [field1, value1, field2, value2, ...]
 */
function objectToHsetArgs(obj) {
  const args = [];
  Object.entries(obj).forEach(([field, value]) => {
    args.push(field, String(value));
  });
  return args;
}

/**
 * Safe hset for multi/pipeline
 * Converts object syntax to flat arguments
 *
 * @param {Object} multi - Redis multi/pipeline object
 * @param {string} key - Redis key
 * @param {Object|Array} fieldsOrObject - Either object {field: value} or flat array [field, value, ...]
 * @returns {Object} - The multi object (for chaining)
 */
function safeHset(multi, key, fieldsOrObject) {
  if (typeof fieldsOrObject === 'object' && !Array.isArray(fieldsOrObject)) {
    // Object syntax - convert to flat args
    const flatArgs = objectToHsetArgs(fieldsOrObject);
    return multi.hset(key, ...flatArgs);
  } else if (Array.isArray(fieldsOrObject)) {
    // Already flat array
    return multi.hset(key, ...fieldsOrObject);
  } else {
    // Assume it's followed by more arguments
    return multi.hset(key, fieldsOrObject);
  }
}

module.exports = {
  objectToHsetArgs,
  safeHset
};
