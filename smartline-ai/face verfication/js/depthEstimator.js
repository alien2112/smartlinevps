/**
 * Optimized Depth Estimator Module
 * Performance improvements: Reduced keypoint tracking, efficient calculations
 */
class DepthEstimator {
    constructor() {
        this.faceMesh = null;
        this.isModelLoaded = false;
        this.depthHistory = [];
        this.maxHistory = 10; // Reduced from 20
        this.cachedResult = null;
        this.frameCount = 0;

        // Key facial points (reduced set)
        this.keyPoints = { noseTip: 1, leftEye: 33, rightEye: 263, chin: 152, forehead: 10 };
    }

    async initialize() {
        try {
            console.log('Loading FaceMesh model (optimized)...');
            this.faceMesh = await faceLandmarksDetection.createDetector(
                faceLandmarksDetection.SupportedModels.MediaPipeFaceMesh,
                { runtime: 'tfjs', refineLandmarks: false, maxFaces: 1 } // refineLandmarks: false for speed
            );
            this.isModelLoaded = true;
            console.log('FaceMesh model loaded successfully');
            return true;
        } catch (error) {
            console.error('Error loading FaceMesh model:', error);
            return false;
        }
    }

    async estimateDepth(videoElement) {
        if (!this.isModelLoaded || !this.faceMesh) return this.cachedResult;

        // Skip frames for performance
        this.frameCount++;
        if (this.frameCount % 2 !== 0 && this.cachedResult) {
            return this.cachedResult;
        }

        try {
            const faces = await this.faceMesh.estimateFaces(videoElement);

            if (faces.length === 0) {
                return { isReal: false, score: 0, reason: 'No face detected' };
            }

            const keypoints = faces[0].keypoints;
            const features = this.extractFeaturesFast(keypoints);
            const depthScore = this.calculateScoreFast(features);

            this.addToHistory(features);
            const consistencyScore = this.calculateConsistencyFast();

            const combinedScore = (depthScore * 0.6) + (consistencyScore * 0.4);

            const result = {
                isReal: combinedScore > 0.6,
                score: Math.round(combinedScore * 100),
                depthScore: Math.round(depthScore * 100),
                consistencyScore: Math.round(consistencyScore * 100),
                features,
                keypoints
            };

            this.cachedResult = result;
            return result;
        } catch (error) {
            console.error('Error estimating depth:', error);
            return this.cachedResult;
        }
    }

    extractFeaturesFast(keypoints) {
        const nose = keypoints[this.keyPoints.noseTip];
        const leftEye = keypoints[this.keyPoints.leftEye];
        const rightEye = keypoints[this.keyPoints.rightEye];
        const chin = keypoints[this.keyPoints.chin];
        const forehead = keypoints[this.keyPoints.forehead];

        const eyeDistance = this.dist(leftEye, rightEye);
        const eyeCenterZ = ((leftEye.z || 0) + (rightEye.z || 0)) * 0.5;
        const noseProtrusion = Math.abs((nose.z || 0) - eyeCenterZ);
        const noseRatio = noseProtrusion / eyeDistance;

        // Z-variance from just 5 key points
        const zValues = [nose.z || 0, leftEye.z || 0, rightEye.z || 0, chin.z || 0, forehead.z || 0];
        const zMean = zValues.reduce((a, b) => a + b, 0) / 5;
        const zVariance = Math.sqrt(zValues.reduce((s, z) => s + Math.pow(z - zMean, 2), 0) / 5);

        return { eyeDistance, noseProtrusion, noseRatio, zVariance, timestamp: Date.now() };
    }

    dist(p1, p2) {
        const dx = p1.x - p2.x, dy = p1.y - p2.y, dz = (p1.z || 0) - (p2.z || 0);
        return Math.sqrt(dx * dx + dy * dy + dz * dz);
    }

    calculateScoreFast(features) {
        let score = 0;
        if (features.noseRatio > 0.05) score += 0.25;
        if (features.noseRatio > 0.10) score += 0.15;
        if (features.zVariance > 0.01) score += 0.20;
        if (features.zVariance > 0.03) score += 0.10;
        score += 0.30; // Base score for having a face
        return Math.min(score, 1.0);
    }

    addToHistory(features) {
        this.depthHistory.push(features);
        if (this.depthHistory.length > this.maxHistory) {
            this.depthHistory.shift();
        }
    }

    calculateConsistencyFast() {
        if (this.depthHistory.length < 3) return 0.5;

        const recent = this.depthHistory.slice(-5);
        const noseRatios = recent.map(f => f.noseRatio);

        const mean = noseRatios.reduce((a, b) => a + b, 0) / noseRatios.length;
        const variance = noseRatios.reduce((s, n) => s + Math.pow(n - mean, 2), 0) / noseRatios.length;

        // Some variation is good (proves movement), but not too much
        if (variance > 0.001 && variance < 0.05) return 0.8;
        return Math.max(0.3, 0.7 - variance * 10);
    }

    getKeypoints() {
        return this.depthHistory.length > 0 ? this.depthHistory[this.depthHistory.length - 1] : null;
    }

    reset() {
        this.depthHistory = [];
        this.cachedResult = null;
        this.frameCount = 0;
    }

    dispose() {
        this.faceMesh = null;
        this.isModelLoaded = false;
        this.reset();
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = DepthEstimator;
}
