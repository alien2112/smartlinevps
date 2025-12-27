
class VideoRecorder {
    constructor() {
        this.mediaRecorder = null;
        this.recordedChunks = [];
        this.isRecording = false;
        this.stream = null;
        this.startTime = null;
        this.recordingDuration = 0;
    }

    /**
     * Initialize recorder with media stream
     * @param {MediaStream} stream - Media stream from webcam
     */
    initialize(stream) {
        this.stream = stream;

        // Check for MediaRecorder support
        if (!window.MediaRecorder) {
            throw new Error('MediaRecorder is not supported in this browser');
        }

        // Determine supported MIME type
        const mimeType = this.getSupportedMimeType();

        try {
            this.mediaRecorder = new MediaRecorder(stream, {
                mimeType: mimeType,
                videoBitsPerSecond: 2500000 // 2.5 Mbps for good quality
            });

            this.setupEventHandlers();
            console.log(`VideoRecorder initialized with ${mimeType}`);
            return true;
        } catch (error) {
            console.error('Error initializing MediaRecorder:', error);
            throw error;
        }
    }

    /**
     * Get supported MIME type for recording
     * @returns {String} Supported MIME type
     */
    getSupportedMimeType() {
        const types = [
            'video/webm;codecs=vp9',
            'video/webm;codecs=vp8',
            'video/webm',
            'video/mp4'
        ];

        for (const type of types) {
            if (MediaRecorder.isTypeSupported(type)) {
                return type;
            }
        }

        return ''; // Use default
    }

    /**
     * Setup event handlers for MediaRecorder
     */
    setupEventHandlers() {
        this.mediaRecorder.ondataavailable = (event) => {
            if (event.data && event.data.size > 0) {
                this.recordedChunks.push(event.data);
            }
        };

        this.mediaRecorder.onstart = () => {
            console.log('Recording started');
            this.isRecording = true;
            this.startTime = Date.now();
        };

        this.mediaRecorder.onstop = () => {
            console.log('Recording stopped');
            this.isRecording = false;
            this.recordingDuration = Date.now() - this.startTime;
        };

        this.mediaRecorder.onerror = (event) => {
            console.error('MediaRecorder error:', event.error);
            this.isRecording = false;
        };
    }

    /**
     * Start recording
     * @param {Number} timeslice - Optional timeslice in milliseconds
     */
    startRecording(timeslice = 1000) {
        if (!this.mediaRecorder) {
            throw new Error('MediaRecorder not initialized');
        }

        if (this.isRecording) {
            console.warn('Recording already in progress');
            return;
        }

        this.recordedChunks = [];
        this.mediaRecorder.start(timeslice);
    }

    /**
     * Stop recording
     * @returns {Promise<Blob>} Promise that resolves with recorded video blob
     */
    stopRecording() {
        return new Promise((resolve, reject) => {
            if (!this.mediaRecorder || !this.isRecording) {
                reject(new Error('No recording in progress'));
                return;
            }

            this.mediaRecorder.onstop = () => {
                this.isRecording = false;
                this.recordingDuration = Date.now() - this.startTime;

                if (this.recordedChunks.length === 0) {
                    reject(new Error('No data recorded'));
                    return;
                }

                const blob = new Blob(this.recordedChunks, {
                    type: this.mediaRecorder.mimeType || 'video/webm'
                });

                console.log(`Recording stopped. Duration: ${this.recordingDuration}ms, Size: ${blob.size} bytes`);
                resolve(blob);
            };

            this.mediaRecorder.stop();
        });
    }

    /**
     * Pause recording
     */
    pauseRecording() {
        if (this.mediaRecorder && this.isRecording && this.mediaRecorder.state === 'recording') {
            this.mediaRecorder.pause();
            console.log('Recording paused');
        }
    }

    /**
     * Resume recording
     */
    resumeRecording() {
        if (this.mediaRecorder && this.mediaRecorder.state === 'paused') {
            this.mediaRecorder.resume();
            console.log('Recording resumed');
        }
    }

    /**
     * Download recorded video
     * @param {Blob} blob - Video blob to download
     * @param {String} filename - Filename for download
     */
    downloadVideo(blob, filename = 'liveness-verification.webm') {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();

        // Cleanup
        setTimeout(() => {
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }, 100);
    }

    /**
     * Get video blob URL for preview
     * @param {Blob} blob - Video blob
     * @returns {String} Object URL
     */
    getVideoURL(blob) {
        return URL.createObjectURL(blob);
    }

    blobToBase64(blob) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    }

    getStatus() {
        return {
            isRecording: this.isRecording,
            state: this.mediaRecorder ? this.mediaRecorder.state : 'inactive',
            duration: this.isRecording ? Date.now() - this.startTime : this.recordingDuration,
            chunksCount: this.recordedChunks.length
        };
    }

    getMetadata() {
        return {
            mimeType: this.mediaRecorder ? this.mediaRecorder.mimeType : null,
            videoBitsPerSecond: this.mediaRecorder ? this.mediaRecorder.videoBitsPerSecond : null,
            duration: this.recordingDuration,
            chunksCount: this.recordedChunks.length,
            totalSize: this.recordedChunks.reduce((sum, chunk) => sum + chunk.size, 0)
        };
    }

    
    reset() {
        if (this.isRecording) {
            this.mediaRecorder.stop();
        }

        this.recordedChunks = [];
        this.isRecording = false;
        this.startTime = null;
        this.recordingDuration = 0;
    }

  
    dispose() {
        this.reset();

        if (this.mediaRecorder) {
            this.mediaRecorder = null;
        }

        this.stream = null;
    }
}
if (typeof module !== 'undefined' && module.exports) {
    module.exports = VideoRecorder;
}
