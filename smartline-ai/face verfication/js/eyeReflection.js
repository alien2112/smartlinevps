/**
 * Optimized Eye Reflection Module
 * Performance: Minimal keypoint usage, efficient region sampling
 */
class EyeReflection {
    constructor() {
        this.isReady = false;
        this.reflectionHistory = [];
        this.maxHistory = 8; // Reduced
        this.lastResult = null;
        this.cachedResult = null;
        this.frameCount = 0;
    }

    async initialize() {
        this.isReady = true;
        console.log('Eye reflection analyzer initialized (optimized)');
        return true;
    }

    analyze(videoElement, canvas, keypoints) {
        if (!this.isReady || !keypoints || keypoints.length < 400) {
            return this.cachedResult;
        }

        // Skip frames
        this.frameCount++;
        if (this.frameCount % 3 !== 0 && this.cachedResult) {
            return this.cachedResult;
        }

        const ctx = canvas.getContext('2d', { willReadFrequently: true });

        // Use small canvas
        const scale = 0.5;
        const w = Math.floor(videoElement.videoWidth * scale);
        const h = Math.floor(videoElement.videoHeight * scale);
        canvas.width = w;
        canvas.height = h;
        ctx.drawImage(videoElement, 0, 0, w, h);

        const imageData = ctx.getImageData(0, 0, w, h);

        // Quick eye region analysis
        const leftEyeScore = this.analyzeEyeRegionFast(imageData, keypoints, 'left', scale);
        const rightEyeScore = this.analyzeEyeRegionFast(imageData, keypoints, 'right', scale);

        const specularScore = (leftEyeScore + rightEyeScore) * 0.5;
        const consistencyScore = Math.abs(leftEyeScore - rightEyeScore) < 0.3 ? 0.8 : 0.5;

        this.addToHistory({ specularScore, consistencyScore });
        const temporalScore = this.calculateTemporalFast();

        const overallScore = specularScore * 0.5 + consistencyScore * 0.25 + temporalScore * 0.25;

        const result = {
            isReal: overallScore > 0.55,
            score: Math.round(overallScore * 100),
            consistencyScore: Math.round(consistencyScore * 100),
            specularScore: Math.round(specularScore * 100),
            temporalScore: Math.round(temporalScore * 100),
            issues: []
        };

        if (specularScore < 0.4) result.issues.push('Missing eye reflections');

        this.lastResult = result;
        this.cachedResult = result;
        return result;
    }

    analyzeEyeRegionFast(imageData, keypoints, side, scale) {
        // Use just center eye point
        const eyeIdx = side === 'left' ? 33 : 263;
        const eye = keypoints[eyeIdx];
        if (!eye) return 0.5;

        const cx = Math.floor(eye.x * scale);
        const cy = Math.floor(eye.y * scale);
        const data = imageData.data;
        const w = imageData.width;

        // Sample 15x15 region around eye center
        const size = 7;
        let brightPixels = 0, totalPixels = 0, totalBrightness = 0;

        for (let dy = -size; dy <= size; dy++) {
            for (let dx = -size; dx <= size; dx++) {
                const x = cx + dx, y = cy + dy;
                if (x < 0 || x >= w || y < 0 || y >= imageData.height) continue;

                const idx = (y * w + x) << 2;
                const brightness = (data[idx] + data[idx + 1] + data[idx + 2]) * 0.333;
                totalBrightness += brightness;
                totalPixels++;
                if (brightness > 200) brightPixels++;
            }
        }

        if (totalPixels === 0) return 0.5;

        const brightRatio = brightPixels / totalPixels;
        // Natural eyes have some specular highlights (1-10% bright pixels)
        if (brightRatio > 0.01 && brightRatio < 0.15) {
            return 0.6 + brightRatio * 2;
        }
        return brightRatio > 0.15 ? 0.4 : 0.3 + brightRatio * 10;
    }

    addToHistory(result) {
        this.reflectionHistory.push(result);
        if (this.reflectionHistory.length > this.maxHistory) {
            this.reflectionHistory.shift();
        }
    }

    calculateTemporalFast() {
        if (this.reflectionHistory.length < 3) return 0.5;

        const recent = this.reflectionHistory.slice(-5);
        const scores = recent.map(r => r.specularScore);
        const mean = scores.reduce((a, b) => a + b, 0) / scores.length;
        const variance = scores.reduce((s, v) => s + Math.pow(v - mean, 2), 0) / scores.length;

        return variance > 0.001 && variance < 0.05 ? 0.8 : Math.max(0.3, 0.7 - variance * 5);
    }

    getResults() { return this.lastResult; }

    reset() {
        this.reflectionHistory = [];
        this.lastResult = null;
        this.cachedResult = null;
        this.frameCount = 0;
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = EyeReflection;
}
