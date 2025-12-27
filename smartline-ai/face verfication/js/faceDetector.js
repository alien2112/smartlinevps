class FaceDetector {
    constructor() {
        this.model = null;
        this.isModelLoaded = false;
        this.detectionInterval = null;
        this.onDetectionCallback = null;
    }

    /**
     * Initialize and load the BlazeFace model
     */
    async initialize() {
        try {
            console.log('Loading BlazeFace model...');
            this.model = await blazeface.load();
            this.isModelLoaded = true;
            console.log('BlazeFace model loaded successfully');
            return true;
        } catch (error) {
            console.error('Error loading BlazeFace model:', error);
            throw new Error('Failed to load face detection model');
        }
    }

    /**
     * Start continuous face detection
     * @param {HTMLVideoElement} videoElement - Video element to detect faces from
     * @param {HTMLCanvasElement} canvas - Canvas for drawing detection boxes
     * @param {Function} callback - Callback function for detection results
     */
    startDetection(videoElement, canvas, callback) {
        if (!this.isModelLoaded) {
            console.error('Model not loaded. Call initialize() first.');
            return;
        }

        this.onDetectionCallback = callback;
        const ctx = canvas.getContext('2d');

        // Set canvas size to match video
        canvas.width = videoElement.videoWidth;
        canvas.height = videoElement.videoHeight;

        // Run detection every 100ms
        this.detectionInterval = setInterval(async () => {
            await this.detectFaces(videoElement, ctx);
        }, 100);
    }

    /**
     * Detect faces in the current video frame
     * @param {HTMLVideoElement} videoElement - Video element
     * @param {CanvasRenderingContext2D} ctx - Canvas context
     */
    async detectFaces(videoElement, ctx) {
        try {
            const predictions = await this.model.estimateFaces(videoElement, false);

            // Clear previous drawings
            ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);

            let detectionResult = {
                faceDetected: predictions.length > 0,
                multipleFaces: predictions.length > 1,
                faceCount: predictions.length,
                predictions: predictions,
                confidence: 0,
                landmarks: null
            };

            if (predictions.length > 0) {
                const face = predictions[0];

                // Calculate confidence (BlazeFace returns probability)
                detectionResult.confidence = Math.round((face.probability[0] || 0) * 100);

                // Get face landmarks
                detectionResult.landmarks = face.landmarks;

                // Draw bounding box
                const start = face.topLeft;
                const end = face.bottomRight;
                const size = [end[0] - start[0], end[1] - start[1]];

                // Draw face box with gradient
                ctx.strokeStyle = predictions.length === 1 ? '#10b981' : '#ef4444';
                ctx.lineWidth = 3;
                ctx.strokeRect(start[0], start[1], size[0], size[1]);

                // Draw landmarks (eyes, nose, mouth, ears)
                if (face.landmarks) {
                    ctx.fillStyle = '#667eea';
                    face.landmarks.forEach(landmark => {
                        ctx.beginPath();
                        ctx.arc(landmark[0], landmark[1], 3, 0, 2 * Math.PI);
                        ctx.fill();
                    });
                }

                // Draw confidence text
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 16px Inter';
                ctx.fillText(`${detectionResult.confidence}%`, start[0], start[1] - 10);
            }

            // Call the callback with detection results
            if (this.onDetectionCallback) {
                this.onDetectionCallback(detectionResult);
            }

        } catch (error) {
            console.error('Error during face detection:', error);
        }
    }

    /**
     * Stop face detection
     */
    stopDetection() {
        if (this.detectionInterval) {
            clearInterval(this.detectionInterval);
            this.detectionInterval = null;
        }
    }

    /**
     * Calculate face position relative to frame center
     * @param {Array} predictions - Face detection predictions
     * @param {Number} videoWidth - Video width
     * @param {Number} videoHeight - Video height
     * @returns {Object} Position information
     */
    getFacePosition(predictions, videoWidth, videoHeight) {
        if (predictions.length === 0) {
            return { centered: false, x: 0, y: 0 };
        }

        const face = predictions[0];
        const start = face.topLeft;
        const end = face.bottomRight;

        const faceCenterX = (start[0] + end[0]) / 2;
        const faceCenterY = (start[1] + end[1]) / 2;

        const videoCenterX = videoWidth / 2;
        const videoCenterY = videoHeight / 2;

        const offsetX = faceCenterX - videoCenterX;
        const offsetY = faceCenterY - videoCenterY;

        // Consider centered if within 15% of frame size
        const threshold = 0.15;
        const centered = Math.abs(offsetX) < videoWidth * threshold &&
            Math.abs(offsetY) < videoHeight * threshold;

        return {
            centered,
            x: offsetX,
            y: offsetY,
            faceCenterX,
            faceCenterY
        };
    }

    /**
     * Calculate face size relative to frame
     * @param {Array} predictions - Face detection predictions
     * @param {Number} videoWidth - Video width
     * @param {Number} videoHeight - Video height
     * @returns {Object} Size information
     */
    getFaceSize(predictions, videoWidth, videoHeight) {
        if (predictions.length === 0) {
            return { appropriate: false, ratio: 0 };
        }

        const face = predictions[0];
        const start = face.topLeft;
        const end = face.bottomRight;

        const faceWidth = end[0] - start[0];
        const faceHeight = end[1] - start[1];

        const faceArea = faceWidth * faceHeight;
        const frameArea = videoWidth * videoHeight;

        const ratio = faceArea / frameArea;

        // Face should occupy 15-40% of frame
        const appropriate = ratio >= 0.15 && ratio <= 0.40;

        return {
            appropriate,
            ratio,
            tooSmall: ratio < 0.15,
            tooLarge: ratio > 0.40
        };
    }

    /**
     * Cleanup resources
     */
    dispose() {
        this.stopDetection();
        if (this.model) {
            this.model.dispose();
            this.model = null;
        }
        this.isModelLoaded = false;
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FaceDetector;
}
