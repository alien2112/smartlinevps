/**
 * Optimized Micro-Expression Module
 * Performance: Reduced landmark tracking, efficient movement detection
 */
class MicroExpression {
    constructor() {
        this.isReady = false;
        this.landmarkHistory = [];
        this.maxHistory = 20; // Reduced from 60
        this.lastResult = null;
        this.cachedResult = null;
        this.frameCount = 0;

        // Track only key points (reduced from 9 regions to 5 key points)
        this.keyIndices = [1, 33, 263, 61, 291]; // nose, leftEye, rightEye, mouth corners
    }

    async initialize() {
        this.isReady = true;
        console.log('Micro-expression analyzer initialized (optimized)');
        return true;
    }

    analyze(keypoints) {
        if (!this.isReady || !keypoints || keypoints.length < 400) {
            return this.cachedResult;
        }

        // Skip frames
        this.frameCount++;
        if (this.frameCount % 2 !== 0 && this.cachedResult) {
            return this.cachedResult;
        }

        // Store only key landmarks
        this.addToHistory(keypoints);

        if (this.landmarkHistory.length < 5) {
            return { isReal: true, score: 50, message: 'Gathering data...', microMovements: 0 };
        }

        const movements = this.analyzeMovementsFast();
        const naturalness = this.evaluateNaturalnessFast(movements);
        const involuntaryScore = this.detectMicroMovementsFast();
        const coordinationScore = this.analyzeCoordinationFast(movements);

        const overallScore = naturalness * 0.35 + involuntaryScore * 0.35 + coordinationScore * 0.30;

        const result = {
            isReal: overallScore > 0.5,
            score: Math.round(overallScore * 100),
            naturalness: Math.round(naturalness * 100),
            involuntaryScore: Math.round(involuntaryScore * 100),
            coordinationScore: Math.round(coordinationScore * 100),
            microMovements: this.countMicroMovementsFast(),
            issues: []
        };

        if (naturalness < 0.4) result.issues.push('Unnatural movement');
        if (involuntaryScore < 0.4) result.issues.push('Missing micro-movements');

        this.lastResult = result;
        this.cachedResult = result;
        return result;
    }

    addToHistory(keypoints) {
        // Store only key points
        const simplified = this.keyIndices.map(idx => ({
            x: keypoints[idx]?.x || 0,
            y: keypoints[idx]?.y || 0,
            z: keypoints[idx]?.z || 0
        }));

        this.landmarkHistory.push({ keypoints: simplified, timestamp: Date.now() });

        if (this.landmarkHistory.length > this.maxHistory) {
            this.landmarkHistory.shift();
        }
    }

    analyzeMovementsFast() {
        if (this.landmarkHistory.length < 2) return { total: 0, max: 0 };

        const curr = this.landmarkHistory[this.landmarkHistory.length - 1].keypoints;
        const prev = this.landmarkHistory[this.landmarkHistory.length - 2].keypoints;

        let totalMovement = 0, maxMovement = 0;

        for (let i = 0; i < curr.length; i++) {
            const dx = curr[i].x - prev[i].x;
            const dy = curr[i].y - prev[i].y;
            const movement = Math.sqrt(dx * dx + dy * dy);
            totalMovement += movement;
            maxMovement = Math.max(maxMovement, movement);
        }

        return { total: totalMovement / curr.length, max: maxMovement };
    }

    evaluateNaturalnessFast(movements) {
        const avg = movements.total;
        // Natural faces have subtle continuous movement (0.1-3.0 range)
        if (avg > 0.05 && avg < 3.0) return 0.6 + avg / 10;
        if (avg < 0.05) return 0.3 + avg * 6; // Too still
        return Math.max(0.3, 1.0 - (avg - 3) / 10); // Too much movement
    }

    detectMicroMovementsFast() {
        if (this.landmarkHistory.length < 8) return 0.5;

        const recent = this.landmarkHistory.slice(-10);
        let twitches = 0;

        for (let i = 2; i < recent.length; i++) {
            const prev = recent[i - 2].keypoints;
            const mid = recent[i - 1].keypoints;
            const curr = recent[i].keypoints;

            // Check eye points for micro-twitches
            for (let j = 1; j <= 2; j++) { // leftEye, rightEye
                const prevY = prev[j].y, midY = mid[j].y, currY = curr[j].y;
                const move1 = midY - prevY, move2 = currY - midY;

                // Direction change = twitch
                if (Math.abs(move1) > 0.3 && Math.abs(move2) > 0.3 &&
                    (move1 > 0) !== (move2 > 0)) {
                    twitches++;
                }
            }
        }

        const twitchRatio = twitches / ((recent.length - 2) * 2);
        if (twitchRatio > 0.01 && twitchRatio < 0.15) return 0.6 + twitchRatio * 2;
        if (twitchRatio < 0.01) return 0.3 + twitchRatio * 30;
        return Math.max(0.3, 1.0 - twitchRatio * 3);
    }

    analyzeCoordinationFast(movements) {
        if (this.landmarkHistory.length < 3) return 0.5;

        const latest = this.landmarkHistory.slice(-3);

        // Check if both eyes move together
        let eyeCorrelation = 0;
        for (let i = 1; i < latest.length; i++) {
            const curr = latest[i].keypoints;
            const prev = latest[i - 1].keypoints;

            const leftMove = Math.abs(curr[1].y - prev[1].y);
            const rightMove = Math.abs(curr[2].y - prev[2].y);

            if (Math.abs(leftMove - rightMove) < 1.0) eyeCorrelation += 0.5;
        }

        return 0.5 + Math.min(eyeCorrelation / (latest.length - 1), 0.5);
    }

    countMicroMovementsFast() {
        if (this.landmarkHistory.length < 3) return 0;

        const recent = this.landmarkHistory.slice(-5);
        let count = 0;

        for (let i = 1; i < recent.length; i++) {
            const curr = recent[i].keypoints;
            const prev = recent[i - 1].keypoints;

            for (let j = 0; j < curr.length; j++) {
                const move = Math.sqrt(
                    Math.pow(curr[j].x - prev[j].x, 2) +
                    Math.pow(curr[j].y - prev[j].y, 2)
                );
                if (move > 0.2 && move < 2.0) count++;
            }
        }

        return count;
    }

    getResults() { return this.lastResult; }

    reset() {
        this.landmarkHistory = [];
        this.lastResult = null;
        this.cachedResult = null;
        this.frameCount = 0;
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = MicroExpression;
}
