class LivenessApp {
    constructor() {
        // Core modules
        this.faceDetector = new FaceDetector();
        this.livenessChallenger = new LivenessChallenger();
        this.videoRecorder = new VideoRecorder();

        // Enterprise modules
        this.depthEstimator = new DepthEstimator();
        this.passiveLiveness = new PassiveLiveness();
        this.antiSpoofing = new AntiSpoofing();
        this.eyeReflection = new EyeReflection();
        this.microExpression = new MicroExpression();

        // DOM elements
        this.elements = {
            welcomeScreen: document.getElementById('welcomeScreen'),
            cameraScreen: document.getElementById('cameraScreen'),
            resultsScreen: document.getElementById('resultsScreen'),
            videoElement: document.getElementById('videoElement'),
            overlayCanvas: document.getElementById('overlayCanvas'),
            faceOutline: document.getElementById('faceOutline'),
            challengeOverlay: document.getElementById('challengeOverlay'),
            challengeIcon: document.getElementById('challengeIcon'),
            challengeText: document.getElementById('challengeText'),
            challengeProgress: document.getElementById('challengeProgress'),
            systemStatus: document.getElementById('systemStatus'),
            detectionStatus: document.getElementById('detectionStatus'),
            confidenceValue: document.getElementById('confidenceValue'),
            challengeCounter: document.getElementById('challengeCounter'),
            recordingIndicator: document.getElementById('recordingIndicator'),
            startBtn: document.getElementById('startBtn'),
            cancelBtn: document.getElementById('cancelBtn'),
            retryBtn: document.getElementById('retryBtn'),
            downloadBtn: document.getElementById('downloadBtn'),
            newVerificationBtn: document.getElementById('newVerificationBtn'),
            resultIcon: document.getElementById('resultIcon'),
            resultTitle: document.getElementById('resultTitle'),
            resultMessage: document.getElementById('resultMessage'),
            verificationTime: document.getElementById('verificationTime'),
            challengesCompleted: document.getElementById('challengesCompleted'),
            avgConfidence: document.getElementById('avgConfidence'),
            timestamp: document.getElementById('timestamp'),
            loadingOverlay: document.getElementById('loadingOverlay'),
            loadingText: document.getElementById('loadingText'),
            // Security score elements
            activeScore: document.getElementById('activeScore'),
            activeScoreValue: document.getElementById('activeScoreValue'),
            passiveScore: document.getElementById('passiveScore'),
            passiveScoreValue: document.getElementById('passiveScoreValue'),
            antiSpoofScore: document.getElementById('antiSpoofScore'),
            antiSpoofScoreValue: document.getElementById('antiSpoofScoreValue'),
            depthScore: document.getElementById('depthScore'),
            depthScoreValue: document.getElementById('depthScoreValue'),
            eyeScore: document.getElementById('eyeScore'),
            eyeScoreValue: document.getElementById('eyeScoreValue'),
            microScore: document.getElementById('microScore'),
            microScoreValue: document.getElementById('microScoreValue'),
            combinedScore: document.getElementById('combinedScore')
        };

        // State
        this.stream = null;
        this.isVerifying = false;
        this.verificationStartTime = null;
        this.detectionData = null;
        this.recordedVideoBlob = null;
        this.confidenceHistory = [];
        this.requiredChallenges = 2; // Only 2 challenges required
        this.completedChallenges = 0;

        // Enterprise state
        this.analysisCanvas = document.createElement('canvas');
        this.passiveAnalysisInterval = null;
        this.securityScores = {
            active: 0,
            passive: 0,
            antiSpoof: 0,
            depth: 0,
            eyeReflection: 0,
            microExpression: 0
        };
        this.latestKeypoints = null;

        this.init();
    }

    async init() {
        this.setupEventListeners();
        await this.loadModels();
        // Initialize translations
        if (typeof Translations !== 'undefined') {
            Translations.init();
        }
    }

    setupEventListeners() {
        this.elements.startBtn.addEventListener('click', () => this.startVerification());
        this.elements.cancelBtn.addEventListener('click', () => this.cancelVerification());
        this.elements.retryBtn.addEventListener('click', () => this.startVerification());
        this.elements.downloadBtn.addEventListener('click', () => this.downloadRecording());
        this.elements.newVerificationBtn.addEventListener('click', () => this.resetApp());
    }

    async loadModels() {
        try {
            this.updateLoadingText(this.t('loadingText'));

            // Load BlazeFace for basic detection
            await this.faceDetector.initialize();
            this.updateLoadingText(this.t('loadingDepth'));

            // Load FaceMesh for depth and micro-expressions
            await this.depthEstimator.initialize();
            this.updateLoadingText(this.t('loadingPassive'));

            // Initialize passive analyzers
            await this.passiveLiveness.initialize();
            await this.antiSpoofing.initialize();
            await this.eyeReflection.initialize();
            await this.microExpression.initialize();

            this.updateLoadingText(this.t('loadingComplete'));
            this.updateSystemStatus(this.t('statusReady'), 'success');

            setTimeout(() => {
                this.elements.loadingOverlay.classList.add('hidden');
            }, 500);
        } catch (error) {
            console.error('Error loading models:', error);
            this.updateLoadingText(this.t('loadingError'));
            this.updateSystemStatus(this.t('statusError'), 'error');
        }
    }

    // Helper method for translations
    t(key) {
        if (typeof Translations !== 'undefined') {
            return Translations.t(key);
        }
        return key;
    }

    async startVerification() {
        try {
            this.showScreen('cameraScreen');
            this.updateSystemStatus(this.t('statusStartingCamera'), 'warning');

            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' },
                audio: false
            });

            this.elements.videoElement.srcObject = this.stream;

            await new Promise((resolve) => {
                this.elements.videoElement.onloadedmetadata = () => {
                    this.elements.videoElement.play();
                    resolve();
                };
            });

            this.videoRecorder.initialize(this.stream);
            this.videoRecorder.startRecording();
            this.elements.recordingIndicator.classList.add('active');

            this.faceDetector.startDetection(
                this.elements.videoElement,
                this.elements.overlayCanvas,
                (data) => this.handleDetection(data)
            );

            // Start passive analysis
            this.startPassiveAnalysis();

            this.isVerifying = true;
            this.verificationStartTime = Date.now();
            this.completedChallenges = 0;
            this.confidenceHistory = [];

            this.updateSystemStatus(this.t('statusVerifying'), 'warning');

            setTimeout(() => {
                this.startNextChallenge();
            }, 2000);

        } catch (error) {
            console.error('Error starting verification:', error);
            alert('Could not access camera. Please ensure camera permissions are granted.');
            this.showScreen('welcomeScreen');
        }
    }

    startPassiveAnalysis() {
        // Run passive analysis every 1000ms (relaxed for better UX like Persona)
        this.passiveAnalysisInterval = setInterval(async () => {
            if (!this.isVerifying) return;

            try {
                // Depth analysis
                const depthResult = await this.depthEstimator.estimateDepth(this.elements.videoElement);
                if (depthResult) {
                    this.securityScores.depth = depthResult.score;
                    this.latestKeypoints = depthResult.keypoints;
                }

                // Passive liveness
                const passiveResult = await this.passiveLiveness.analyze(
                    this.elements.videoElement,
                    this.analysisCanvas
                );
                if (passiveResult) {
                    this.securityScores.passive = passiveResult.overallScore;
                }

                // Anti-spoofing
                const antiSpoofResult = await this.antiSpoofing.analyze(
                    this.elements.videoElement,
                    this.analysisCanvas,
                    depthResult,
                    passiveResult
                );
                if (antiSpoofResult) {
                    this.securityScores.antiSpoof = antiSpoofResult.overallScore;
                }

                // Eye reflection (needs FaceMesh keypoints)
                if (depthResult && depthResult.keypoints) {
                    const eyeResult = this.eyeReflection.analyze(
                        this.elements.videoElement,
                        this.analysisCanvas,
                        depthResult.keypoints
                    );
                    if (eyeResult) {
                        this.securityScores.eyeReflection = eyeResult.score;
                    }

                    // Micro-expressions
                    const microResult = this.microExpression.analyze(depthResult.keypoints);
                    if (microResult) {
                        this.securityScores.microExpression = microResult.score;
                    }
                }

            } catch (error) {
                console.error('Error in passive analysis:', error);
            }
        }, 1000); // Increased from 750ms to 1000ms
    }

    stopPassiveAnalysis() {
        if (this.passiveAnalysisInterval) {
            clearInterval(this.passiveAnalysisInterval);
            this.passiveAnalysisInterval = null;
        }
    }

    handleDetection(data) {
        this.detectionData = data;

        if (data.faceDetected) {
            if (data.multipleFaces) {
                this.updateDetectionStatus(this.t('multipleFaces'));
                this.elements.faceOutline.style.borderColor = '#ef4444';
            } else {
                this.updateDetectionStatus(this.t('faceDetected'));
                this.elements.faceOutline.style.borderColor = '#10b981';
                this.confidenceHistory.push(data.confidence);
            }
            this.updateConfidence(data.confidence);
        } else {
            this.updateDetectionStatus(this.t('noFaceDetected'));
            this.elements.faceOutline.style.borderColor = '#667eea';
            this.updateConfidence(0);
        }

        if (this.livenessChallenger.isActive && data.faceDetected && !data.multipleFaces) {
            this.livenessChallenger.processDetection(data, (result) => {
                this.handleChallengeComplete(result);
            });
        }
    }

    startNextChallenge() {
        if (this.completedChallenges >= this.requiredChallenges) {
            this.completeVerification(true);
            return;
        }

        this.livenessChallenger.startChallenge(
            (result) => this.handleChallengeComplete(result),
            (progress) => this.updateChallengeProgress(progress)
        );

        const challenge = this.livenessChallenger.getCurrentChallenge();
        if (challenge) {
            this.showChallenge(challenge);
        }
    }

    handleChallengeComplete(result) {
        console.log('Challenge completed:', result);
        this.hideChallenge();

        if (result.success) {
            this.completedChallenges++;
            this.updateChallengeCounter();
            setTimeout(() => this.startNextChallenge(), 1000);
        } else {
            setTimeout(() => this.startNextChallenge(), 1000);
        }
    }

    async completeVerification(success) {
        this.isVerifying = false;
        this.stopPassiveAnalysis();

        try {
            this.recordedVideoBlob = await this.videoRecorder.stopRecording();
        } catch (error) {
            console.error('Error stopping recording:', error);
        }

        this.faceDetector.stopDetection();

        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
        }

        // Calculate active liveness score
        this.securityScores.active = Math.round((this.completedChallenges / this.requiredChallenges) * 100);

        const verificationDuration = Date.now() - this.verificationStartTime;
        const avgConfidence = this.confidenceHistory.length > 0
            ? Math.round(this.confidenceHistory.reduce((a, b) => a + b, 0) / this.confidenceHistory.length)
            : 0;

        // Calculate combined score (Active + Passive + Anti-Spoof)
        const combinedScore = this.calculateCombinedScore();
        // Lowered threshold from 70% to 55% for Persona-like realistic verification
        const finalSuccess = success && combinedScore >= 55;

        this.showResults(finalSuccess, verificationDuration, avgConfidence, combinedScore);
    }

    calculateCombinedScore() {
        // Persona-like weights - focus more on active liveness, less on passive checks
        const weights = {
            active: 0.45,      // 45% - Active challenges (increased for UX)
            passive: 0.20,     // 20% - Passive liveness
            antiSpoof: 0.15,   // 15% - Anti-spoofing (reduced)
            depth: 0.08,       // 8% - Depth analysis (reduced)
            eyeReflection: 0.07, // 7% - Eye reflection (reduced)
            microExpression: 0.05 // 5% - Micro-expressions (reduced)
        };

        const score =
            (this.securityScores.active * weights.active) +
            (this.securityScores.passive * weights.passive) +
            (this.securityScores.antiSpoof * weights.antiSpoof) +
            (this.securityScores.depth * weights.depth) +
            (this.securityScores.eyeReflection * weights.eyeReflection) +
            (this.securityScores.microExpression * weights.microExpression);

        return Math.round(score);
    }

    showResults(success, duration, avgConfidence, combinedScore) {
        this.showScreen('resultsScreen');

        if (success) {
            this.elements.resultIcon.classList.add('success');
            this.elements.resultIcon.classList.remove('error');
            this.elements.resultTitle.textContent = this.t('resultSuccess');
            this.elements.resultMessage.textContent = this.t('resultSuccessMsg');
        } else {
            this.elements.resultIcon.classList.add('error');
            this.elements.resultIcon.classList.remove('success');
            this.elements.resultTitle.textContent = this.t('resultFailed');
            this.elements.resultMessage.textContent = this.t('resultFailedMsg');
        }

        this.elements.verificationTime.textContent = `${(duration / 1000).toFixed(1)}s`;
        this.elements.challengesCompleted.textContent = `${this.completedChallenges}/${this.requiredChallenges}`;
        this.elements.avgConfidence.textContent = `${avgConfidence}%`;
        this.elements.timestamp.textContent = new Date().toLocaleString();

        // Update security scores display
        this.updateScoreDisplay('activeScore', 'activeScoreValue', this.securityScores.active);
        this.updateScoreDisplay('passiveScore', 'passiveScoreValue', this.securityScores.passive);
        this.updateScoreDisplay('antiSpoofScore', 'antiSpoofScoreValue', this.securityScores.antiSpoof);
        this.updateScoreDisplay('depthScore', 'depthScoreValue', this.securityScores.depth);
        this.updateScoreDisplay('eyeScore', 'eyeScoreValue', this.securityScores.eyeReflection);
        this.updateScoreDisplay('microScore', 'microScoreValue', this.securityScores.microExpression);

        // Combined score
        if (this.elements.combinedScore) {
            this.elements.combinedScore.textContent = `${combinedScore}%`;
            const combinedContainer = this.elements.combinedScore.parentElement;
            if (combinedContainer) {
                combinedContainer.classList.remove('success', 'failure');
                combinedContainer.classList.add(success ? 'success' : 'failure');
            }
        }

        this.updateSystemStatus(success ? this.t('statusVerified') : this.t('statusFailed'), success ? 'success' : 'error');
    }

    updateScoreDisplay(barId, valueId, score) {
        const bar = this.elements[barId];
        const value = this.elements[valueId];

        if (bar) {
            bar.style.width = `${score}%`;
            bar.classList.remove('high', 'medium', 'low');
            if (score >= 70) bar.classList.add('high');
            else if (score >= 50) bar.classList.add('medium');
            else bar.classList.add('low');
        }

        if (value) {
            value.textContent = `${score}%`;
        }
    }

    cancelVerification() {
        if (this.isVerifying) {
            this.completeVerification(false);
        } else {
            this.resetApp();
        }
    }

    downloadRecording() {
        if (this.recordedVideoBlob) {
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            this.videoRecorder.downloadVideo(this.recordedVideoBlob, `liveness-verification-${timestamp}.webm`);
        }
    }

    resetApp() {
        this.faceDetector.stopDetection();
        this.videoRecorder.reset();
        this.livenessChallenger.reset();
        this.stopPassiveAnalysis();

        // Reset enterprise modules
        this.depthEstimator.reset();
        this.passiveLiveness.reset();
        this.antiSpoofing.reset();
        this.eyeReflection.reset();
        this.microExpression.reset();

        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }

        this.isVerifying = false;
        this.verificationStartTime = null;
        this.detectionData = null;
        this.recordedVideoBlob = null;
        this.confidenceHistory = [];
        this.completedChallenges = 0;
        this.securityScores = { active: 0, passive: 0, antiSpoof: 0, depth: 0, eyeReflection: 0, microExpression: 0 };

        this.showScreen('welcomeScreen');
        this.updateSystemStatus('Ready', 'success');
        this.elements.recordingIndicator.classList.remove('active');
    }

    showChallenge(challenge) {
        this.elements.challengeIcon.textContent = challenge.icon;
        this.elements.challengeText.textContent = challenge.instruction;
        this.elements.challengeProgress.style.width = '0%';
        this.elements.challengeOverlay.classList.add('active');
    }

    hideChallenge() {
        this.elements.challengeOverlay.classList.remove('active');
    }

    updateChallengeProgress(progress) {
        this.elements.challengeProgress.style.width = `${progress}%`;
    }

    updateChallengeCounter() {
        this.elements.challengeCounter.textContent = `${this.completedChallenges}/${this.requiredChallenges}`;
    }

    updateDetectionStatus(status) {
        this.elements.detectionStatus.textContent = status;
    }

    updateConfidence(confidence) {
        this.elements.confidenceValue.textContent = `${confidence}%`;
    }

    updateSystemStatus(text, type = 'info') {
        const statusDot = this.elements.systemStatus.querySelector('.status-dot');
        const statusText = this.elements.systemStatus.querySelector('.status-text');
        statusText.textContent = text;
        statusDot.style.background = {
            'success': '#10b981', 'warning': '#f59e0b', 'error': '#ef4444', 'info': '#667eea'
        }[type] || '#667eea';
    }

    updateLoadingText(text) {
        this.elements.loadingText.textContent = text;
    }

    showScreen(screenId) {
        this.elements.welcomeScreen.classList.remove('active');
        this.elements.cameraScreen.classList.remove('active');
        this.elements.resultsScreen.classList.remove('active');
        const screenElement = document.getElementById(screenId);
        if (screenElement) screenElement.classList.add('active');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.livenessApp = new LivenessApp();
});
