// ============================================
// 👤 USER PERSONALIZATION ENGINE (V3.1)
// ============================================

/**
 * Manages user preferences, favorite locations, and behavioral patterns
 */
class PersonalizationEngine {
  constructor() {
      // In-memory cache (should be backed by Redis/DB in production)
      this.userProfiles = new Map();
      this.TTL = 30 * 60 * 1000; // 30 minutes cache
  }

  /**
   * Get or create user profile
   * @param {string} userId 
   * @param {Object} dbConnection - Database connection for fetching stored preferences
   */
  async getProfile(userId, dbConnection = null) {
      // Check cache first
      const cached = this.userProfiles.get(userId);
      if (cached && Date.now() - cached.timestamp < this.TTL) {
          return cached.profile;
      }

      // Default profile structure
      let profile = {
          userId,
          preferred_language: null,
          favorite_locations: [],
          recent_destinations: [],
          preferred_vehicle_type: null,
          payment_method: null,
          accessibility_needs: [],
          communication_style: 'standard', // 'concise', 'detailed', 'standard'
          trip_history_summary: {
              total_trips: 0,
              avg_rating_given: 0,
              most_used_pickup: null,
              most_used_destination: null
          },
          flags: {
              is_vip: false,
              has_corporate_account: false,
              requires_special_assistance: false
          },
          created_at: Date.now(),
          updated_at: Date.now()
      };

      // Fetch from database if connection provided
      if (dbConnection) {
          try {
              profile = await this.fetchFromDatabase(userId, dbConnection, profile);
          } catch (error) {
              console.error(`[Personalization] DB fetch failed for ${userId}:`, error.message);
          }
      }

      // Cache the profile
      this.userProfiles.set(userId, {
          profile,
          timestamp: Date.now()
      });

      return profile;
  }

  /**
   * Fetch user data from database
   */
  async fetchFromDatabase(userId, db, defaultProfile) {
      const profile = { ...defaultProfile };

      // Fetch user preferences
      const [userRows] = await db.query(
          `SELECT preferred_language, preferred_vehicle_type, payment_method 
           FROM users WHERE id = ? LIMIT 1`,
          [userId]
      );

      if (userRows.length > 0) {
          const user = userRows[0];
          profile.preferred_language = user.preferred_language;
          profile.preferred_vehicle_type = user.preferred_vehicle_type;
          profile.payment_method = user.payment_method;
      }

      // Fetch favorite locations
      const [locationRows] = await db.query(
          `SELECT name, address, lat, lng, type 
           FROM favorite_locations 
           WHERE user_id = ? 
           ORDER BY usage_count DESC 
           LIMIT 5`,
          [userId]
      );

      profile.favorite_locations = locationRows.map(loc => ({
          name: loc.name,
          address: loc.address,
          coordinates: { lat: loc.lat, lng: loc.lng },
          type: loc.type // 'home', 'work', 'other'
      }));

      // Fetch recent destinations (last 10 trips)
      const [recentRows] = await db.query(
          `SELECT DISTINCT destination_address, destination_lat, destination_lng 
           FROM trips 
           WHERE user_id = ? AND status = 'completed'
           ORDER BY created_at DESC 
           LIMIT 10`,
          [userId]
      );

      profile.recent_destinations = recentRows.map(r => ({
          address: r.destination_address,
          coordinates: { lat: r.destination_lat, lng: r.destination_lng }
      }));

      // Fetch trip history summary
      const [summaryRows] = await db.query(
          `SELECT 
              COUNT(*) as total_trips,
              AVG(rating_given) as avg_rating
           FROM trips 
           WHERE user_id = ? AND status = 'completed'`,
          [userId]
      );

      if (summaryRows.length > 0) {
          profile.trip_history_summary.total_trips = summaryRows[0].total_trips || 0;
          profile.trip_history_summary.avg_rating_given = summaryRows[0].avg_rating || 0;
      }

      // Check VIP status
      const [vipRows] = await db.query(
          `SELECT is_vip, corporate_account_id FROM users WHERE id = ? LIMIT 1`,
          [userId]
      );

      if (vipRows.length > 0) {
          profile.flags.is_vip = !!vipRows[0].is_vip;
          profile.flags.has_corporate_account = !!vipRows[0].corporate_account_id;
      }

      profile.updated_at = Date.now();
      return profile;
  }

  /**
   * Update user preference in cache (and optionally DB)
   */
  async updatePreference(userId, key, value, dbConnection = null) {
      const profile = await this.getProfile(userId);
      
      if (profile.hasOwnProperty(key)) {
          profile[key] = value;
          profile.updated_at = Date.now();
          
          this.userProfiles.set(userId, {
              profile,
              timestamp: Date.now()
          });

          // Persist to database if connection provided
          if (dbConnection && ['preferred_language', 'preferred_vehicle_type', 'payment_method'].includes(key)) {
              try {
                  await dbConnection.query(
                      `UPDATE users SET ${key} = ?, updated_at = NOW() WHERE id = ?`,
                      [value, userId]
                  );
              } catch (error) {
                  console.error(`[Personalization] DB update failed:`, error.message);
              }
          }
      }

      return profile;
  }

  /**
   * Get personalized quick replies based on user history
   */
  getPersonalizedQuickReplies(profile, context = 'default') {
      const replies = [];

      if (context === 'booking_start' || context === 'default') {
          // Add favorite locations as quick replies
          if (profile.favorite_locations.length > 0) {
              const home = profile.favorite_locations.find(l => l.type === 'home');
              const work = profile.favorite_locations.find(l => l.type === 'work');

              if (home) replies.push(`🏠 Home (${home.name})`);
              if (work) replies.push(`🏢 Work (${work.name})`);
          }

          // Add recent destination if available
          if (profile.recent_destinations.length > 0) {
              const recent = profile.recent_destinations[0];
              replies.push(`📍 ${recent.address.substring(0, 30)}...`);
          }
      }

      if (context === 'vehicle_selection' && profile.preferred_vehicle_type) {
          // Prioritize preferred vehicle type
          replies.unshift(`⭐ ${profile.preferred_vehicle_type} (Your usual)`);
      }

      return replies.slice(0, 4); // Max 4 quick replies
  }

  /**
   * Generate personalized greeting
   */
  getPersonalizedGreeting(profile, language = 'en') {
      const greetings = {
          en: {
              new_user: "Welcome to SmartLine! How can I help you today?",
              returning: "Welcome back! Ready to book a ride?",
              vip: "Welcome back! As a valued customer, you have priority booking available.",
              frequent: `Welcome back! Heading to ${profile.trip_history_summary.most_used_destination || 'your usual spot'}?`
          },
          ar: {
              new_user: "أهلاً بيك في سمارت لاين! إزاي أقدر أساعدك؟",
              returning: "أهلاً بيك تاني! عايز تحجز رحلة؟",
              vip: "أهلاً بيك! كعميل مميز، ليك أولوية في الحجز.",
              frequent: `أهلاً بيك تاني! رايح ${profile.trip_history_summary.most_used_destination || 'المكان المعتاد'}؟`
          }
      };

      const lang = language === 'ar' ? 'ar' : 'en';
      
      if (profile.flags.is_vip) {
          return greetings[lang].vip;
      } else if (profile.trip_history_summary.total_trips > 10) {
          return greetings[lang].frequent;
      } else if (profile.trip_history_summary.total_trips > 0) {
          return greetings[lang].returning;
      }
      
      return greetings[lang].new_user;
  }

  /**
   * Clear user from cache
   */
  clearCache(userId) {
      this.userProfiles.delete(userId);
  }

  /**
   * Clear all expired cache entries
   */
  cleanupCache() {
      const now = Date.now();
      for (const [userId, data] of this.userProfiles.entries()) {
          if (now - data.timestamp > this.TTL) {
              this.userProfiles.delete(userId);
          }
      }
  }

  /**
   * Get cache stats
   */
  getStats() {
      return {
          cachedProfiles: this.userProfiles.size,
          ttlMinutes: this.TTL / 60000
      };
  }
}

// Cleanup interval
const engine = new PersonalizationEngine();
setInterval(() => engine.cleanupCache(), 5 * 60 * 1000); // Every 5 minutes

module.exports = engine;