// ============================================
// ⚡ CIRCUIT BREAKER (Resilience Pattern)
// ============================================

/**
 * States: CLOSED (normal), OPEN (failing), HALF_OPEN (testing)
 */
const STATES = {
  CLOSED: 'CLOSED',
  OPEN: 'OPEN',
  HALF_OPEN: 'HALF_OPEN'
};

class CircuitBreaker {
  constructor(options = {}) {
      this.name = options.name || 'default';
      this.failureThreshold = options.failureThreshold || 5;
      this.successThreshold = options.successThreshold || 2;
      this.timeout = options.timeout || 30000; // 30 seconds
      this.resetTimeout = options.resetTimeout || 60000; // 60 seconds

      this.state = STATES.CLOSED;
      this.failures = 0;
      this.successes = 0;
      this.lastFailureTime = null;
      this.nextAttempt = null;

      // Metrics
      this.metrics = {
          totalCalls: 0,
          successfulCalls: 0,
          failedCalls: 0,
          rejectedCalls: 0,
          lastStateChange: Date.now()
      };
  }

  /**
   * Execute a function with circuit breaker protection
   * @param {Function} fn - Async function to execute
   * @param {Function} fallback - Optional fallback function
   */
  async execute(fn, fallback = null) {
      this.metrics.totalCalls++;

      // Check if circuit is open
      if (this.state === STATES.OPEN) {
          if (Date.now() >= this.nextAttempt) {
              // Try half-open
              this.state = STATES.HALF_OPEN;
              console.log(`[CircuitBreaker:${this.name}] Entering HALF_OPEN state`);
          } else {
              this.metrics.rejectedCalls++;
              console.log(`[CircuitBreaker:${this.name}] Call rejected - circuit OPEN`);
              
              if (fallback) {
                  return await fallback();
              }
              
              throw new Error(`Circuit breaker is OPEN for ${this.name}`);
          }
      }

      try {
          // Execute with timeout
          const result = await this.executeWithTimeout(fn);
          this.onSuccess();
          return result;
      } catch (error) {
          this.onFailure(error);
          
          if (fallback) {
              console.log(`[CircuitBreaker:${this.name}] Using fallback`);
              return await fallback();
          }
          
          throw error;
      }
  }

  /**
   * Execute function with timeout
   */
  async executeWithTimeout(fn) {
      return new Promise(async (resolve, reject) => {
          const timer = setTimeout(() => {
              reject(new Error(`Circuit breaker timeout after ${this.timeout}ms`));
          }, this.timeout);

          try {
              const result = await fn();
              clearTimeout(timer);
              resolve(result);
          } catch (error) {
              clearTimeout(timer);
              reject(error);
          }
      });
  }

  /**
   * Handle successful call
   */
  onSuccess() {
      this.metrics.successfulCalls++;
      this.failures = 0;

      if (this.state === STATES.HALF_OPEN) {
          this.successes++;
          if (this.successes >= this.successThreshold) {
              this.state = STATES.CLOSED;
              this.successes = 0;
              this.metrics.lastStateChange = Date.now();
              console.log(`[CircuitBreaker:${this.name}] Circuit CLOSED - service recovered`);
          }
      }
  }

  /**
   * Handle failed call
   */
  onFailure(error) {
      this.metrics.failedCalls++;
      this.failures++;
      this.lastFailureTime = Date.now();

      console.error(`[CircuitBreaker:${this.name}] Failure #${this.failures}: ${error.message}`);

      if (this.state === STATES.HALF_OPEN) {
          // Immediately open on failure in half-open state
          this.tripCircuit();
      } else if (this.failures >= this.failureThreshold) {
          this.tripCircuit();
      }
  }

  /**
   * Trip the circuit to OPEN state
   */
  tripCircuit() {
      this.state = STATES.OPEN;
      this.nextAttempt = Date.now() + this.resetTimeout;
      this.successes = 0;
      this.metrics.lastStateChange = Date.now();
      console.log(`[CircuitBreaker:${this.name}] Circuit OPEN - will retry at ${new Date(this.nextAttempt).toISOString()}`);
  }

  /**
   * Force reset the circuit
   */
  reset() {
      this.state = STATES.CLOSED;
      this.failures = 0;
      this.successes = 0;
      this.nextAttempt = null;
      this.metrics.lastStateChange = Date.now();
      console.log(`[CircuitBreaker:${this.name}] Circuit manually reset`);
  }

  /**
   * Get circuit status
   */
  getStatus() {
      return {
          name: this.name,
          state: this.state,
          failures: this.failures,
          successes: this.successes,
          nextAttempt: this.nextAttempt ? new Date(this.nextAttempt).toISOString() : null,
          metrics: this.metrics
      };
  }
}

// Pre-configured circuit breakers for different services
const circuits = {
  groq: new CircuitBreaker({
      name: 'groq-llm',
      failureThreshold: 3,
      timeout: 30000,
      resetTimeout: 60000
  }),
  database: new CircuitBreaker({
      name: 'mysql-db',
      failureThreshold: 5,
      timeout: 10000,
      resetTimeout: 30000
  }),
  maps: new CircuitBreaker({
      name: 'maps-api',
      failureThreshold: 3,
      timeout: 15000,
      resetTimeout: 45000
  })
};

/**
* Get all circuit statuses
*/
function getAllStatuses() {
  const statuses = {};
  for (const [name, circuit] of Object.entries(circuits)) {
      statuses[name] = circuit.getStatus();
  }
  return statuses;
}

module.exports = {
  CircuitBreaker,
  circuits,
  getAllStatuses,
  STATES
};