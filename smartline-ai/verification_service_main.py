
from fastapi import FastAPI, HTTPException, Header, Depends
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Dict, List, Optional, Any
import httpx
import os
import asyncio
import threading
import time
from dotenv import load_dotenv
import cv2
import numpy as np
from production_egyptian_id_verifier_enhanced import IDVerificationService

# Load environment variables
load_dotenv()

# Configuration
API_KEY = os.getenv("FASTAPI_VERIFICATION_KEY", "your-secret-key")
IDLE_TIMEOUT_SECONDS = int(os.getenv("KYC_IDLE_TIMEOUT", 1800))  # 30 minutes default

# ==================== Auto-Shutdown Manager ====================

class IdleShutdownManager:
    """Manages auto-shutdown after period of inactivity."""
    
    def __init__(self, timeout_seconds: int = 1800):
        self.timeout_seconds = timeout_seconds
        self.last_activity = time.time()
        self._shutdown_task: Optional[asyncio.Task] = None
        self._lock = threading.Lock()
    
    def ping(self):
        """Reset the idle timer - call this on each request."""
        with self._lock:
            self.last_activity = time.time()
            print(f"[KYC] Activity ping - shutdown timer reset to {self.timeout_seconds}s")
    
    def get_remaining_seconds(self) -> int:
        """Get seconds remaining before auto-shutdown."""
        elapsed = time.time() - self.last_activity
        remaining = max(0, self.timeout_seconds - int(elapsed))
        return remaining
    
    async def start_shutdown_watcher(self):
        """Background task that monitors for idle timeout."""
        print(f"[KYC] Auto-shutdown enabled - will shutdown after {self.timeout_seconds}s of inactivity")
        while True:
            await asyncio.sleep(60)  # Check every minute
            remaining = self.get_remaining_seconds()
            
            if remaining <= 0:
                print("[KYC] Idle timeout reached - shutting down...")
                os._exit(0)  # Force exit
            elif remaining <= 300:  # Less than 5 minutes
                print(f"[KYC] Warning: Auto-shutdown in {remaining}s (no activity)")

shutdown_manager = IdleShutdownManager(IDLE_TIMEOUT_SECONDS)

# ==================== Pydantic Models ====================

class ReasonCode(BaseModel):
    code: str
    message: str

class VerifyRequest(BaseModel):
    session_id: str
    media: Dict[str, str]  # {kind: signed_url}

class VerifyResponse(BaseModel):
    session_id: str
    liveness_passed: bool
    liveness_details: Dict[str, Any]
    doc_auth_score: float
    doc_extracted_fields: Dict[str, Any]
    suggested_decision: str
    reason_codes: List[ReasonCode]

# ==================== Helper Functions ====================

def _bytes_to_numpy(data: bytes) -> np.ndarray:
    """Convert image bytes to numpy array (opencv format)."""
    nparr = np.frombuffer(data, np.uint8)
    return cv2.imdecode(nparr, cv2.IMREAD_COLOR)

def _parse_dob_from_id(id_number: str) -> Optional[str]:
    """Parse DOB from Egyptian ID (14 digits)."""
    if not id_number or len(id_number) != 14:
        return None
    
    century_code = int(id_number[0])
    year_part = int(id_number[1:3])
    month_part = int(id_number[3:5])
    day_part = int(id_number[5:7])
    
    century = 1900 if century_code == 2 else 2000
    full_year = century + year_part
    
    try:
        return f"{full_year}-{month_part:02d}-{day_part:02d}"
    except:
        return None

def check_liveness(image: np.ndarray) -> Dict[str, Any]:
    """
    Check if the selfie is a real human (liveness detection).
    Detects signs of a photo-of-photo or screen capture.
    """
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    
    # 1. Image quality checks
    blur_score = cv2.Laplacian(gray, cv2.CV_64F).var()
    is_blurry = blur_score < 100.0
    
    avg_brightness = np.mean(gray)
    is_dark = avg_brightness < 40
    is_overexposed = avg_brightness > 220
    
    # 2. Face detection - must have exactly one face
    face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')
    faces = face_cascade.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(80, 80))
    face_count = len(faces)
    has_face = face_count == 1
    
    # 3. Screen/print detection - check for moire patterns and unnatural edges
    # High frequency noise detection (screens have regular pixel patterns)
    edges = cv2.Canny(gray, 50, 150)
    edge_density = np.sum(edges > 0) / edges.size
    
    # FFT analysis for moire pattern detection
    f_transform = np.fft.fft2(gray.astype(np.float32))
    f_shift = np.fft.fftshift(f_transform)
    magnitude = np.abs(f_shift)
    
    # Check for regular patterns (screens have peaks at specific frequencies)
    center_y, center_x = magnitude.shape[0] // 2, magnitude.shape[1] // 2
    # Exclude DC component
    magnitude[center_y-5:center_y+5, center_x-5:center_x+5] = 0
    max_mag = magnitude.max()
    avg_mag = magnitude.mean()
    pattern_ratio = max_mag / avg_mag if avg_mag > 0 else 0
    
    # High pattern ratio indicates regular patterns (possibly a screen)
    has_moire = pattern_ratio > 50
    
    # 4. Skin tone detection - real faces have natural skin tones
    hsv = cv2.cvtColor(image, cv2.COLOR_BGR2HSV)
    # Skin tone range in HSV
    skin_mask = cv2.inRange(hsv, np.array([0, 20, 70]), np.array([20, 255, 255]))
    skin_ratio = np.sum(skin_mask > 0) / skin_mask.size
    has_skin_tone = skin_ratio > 0.05  # At least 5% skin pixels
    
    # 5. Texture analysis - real faces have natural texture variation
    texture_score = 0.0
    if face_count > 0:
        x, y, w, h = faces[0]
        face_region = gray[y:y+h, x:x+w]
        if face_region.size > 0:
            # LBP-like texture measure
            texture_score = np.std(face_region)
    has_natural_texture = texture_score > 20
    
    # Decision
    quality_passed = not (is_blurry or is_dark or is_overexposed)
    liveness_passed = has_face and not has_moire and has_skin_tone and has_natural_texture and quality_passed
    
    message = "Liveness check passed"
    if not has_face:
        message = f"Face detection failed (found {face_count} faces, need exactly 1)"
    elif has_moire:
        message = "Possible screen/photo detected (moire pattern)"
    elif not has_skin_tone:
        message = "No natural skin tone detected"
    elif not has_natural_texture:
        message = "Unnatural face texture (possible print/screen)"
    elif is_blurry:
        message = "Image is too blurry"
    elif is_dark:
        message = "Image is too dark"
    elif is_overexposed:
        message = "Image is overexposed"
    
    return {
        "passed": liveness_passed,
        "face_detected": has_face,
        "face_count": face_count,
        "blur_score": float(blur_score),
        "brightness_score": float(avg_brightness),
        "skin_tone_ratio": float(skin_ratio),
        "texture_score": float(texture_score),
        "moire_detected": has_moire,
        "message": message
    }

async def download_media(media_urls: Dict[str, str]) -> Dict[str, bytes]:
    """Download media files from signed URLs."""
    downloaded = {}
    async with httpx.AsyncClient(timeout=30.0) as client:
        for kind, url in media_urls.items():
            if url:
                try:
                    response = await client.get(url)
                    if response.status_code == 200:
                        downloaded[kind] = response.content
                except Exception as e:
                    print(f"Error downloading {kind}: {e}")
    return downloaded

def determine_decision(liveness_passed: bool, doc_auth: float) -> str:
    """Determine suggested decision based on liveness and document authenticity."""
    if not liveness_passed:
        return "rejected"
    
    if doc_auth >= 70:
        return "approved"
    elif doc_auth >= 50:
        return "manual_review"
    else:
        return "rejected"

# ==================== Dependencies ====================

async def verify_api_key(x_api_key: str = Header(...)):
    """Verify the API key from request header."""
    if x_api_key != API_KEY:
        raise HTTPException(status_code=401, detail="Invalid API key")
    return x_api_key

# ==================== Initialization ====================

# Initialize ID Verification Service (loads OCR models)
print("Initializing ID Verification Service...")
id_service = IDVerificationService()
print("ID Verification Service initialized.")

# ==================== App Setup ====================

app = FastAPI(
    title="KYC Verification Service",
    description="Internal microservice for driver KYC verification",
    version="1.0.0",
)

# CORS (restrictive - only internal access)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost", "http://127.0.0.1"],
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)

@app.on_event("startup")
async def startup_event():
    """Start the idle shutdown watcher on app startup."""
    asyncio.create_task(shutdown_manager.start_shutdown_watcher())

# ==================== Endpoints ====================

@app.get("/health")
async def health_check():
    """Health check endpoint."""
    shutdown_manager.ping()  # Keep alive on health checks too
    remaining = shutdown_manager.get_remaining_seconds()
    return {
        "status": "healthy", 
        "service": "verification",
        "auto_shutdown_in_seconds": remaining
    }

@app.get("/internal/status")
async def get_status():
    """Get service status including shutdown timer."""
    return {
        "status": "running",
        "idle_timeout_seconds": shutdown_manager.timeout_seconds,
        "remaining_seconds": shutdown_manager.get_remaining_seconds()
    }

@app.post("/internal/verify", response_model=VerifyResponse)
async def verify_documents(
    request: VerifyRequest,
    api_key: str = Depends(verify_api_key)
):
    """
    Main verification endpoint.
    1. Liveness detection - verify selfie is a real human (not photo of photo)
    2. ID document verification - validate Egyptian National ID authenticity
    """
    # Reset idle timer on each verification request
    shutdown_manager.ping()
    
    session_id = request.session_id
    media = request.media
    
    reason_codes = []
    
    # Initialize response values
    liveness_passed = False
    liveness_details = {}
    doc_auth_score = 0.0
    doc_extracted_fields = {}
    
    try:
        # 1. Download media files
        downloaded_media = await download_media(media)
        
        # 2. Liveness detection on selfie
        if "selfie" in downloaded_media:
            selfie_np = _bytes_to_numpy(downloaded_media["selfie"])
            liveness_result = check_liveness(selfie_np)
            liveness_passed = liveness_result["passed"]
            liveness_details = liveness_result
            if not liveness_passed:
                reason_codes.append(ReasonCode(
                    code="LIVENESS_FAILED",
                    message=liveness_result.get("message", "Liveness check failed")
                ))
        else:
            reason_codes.append(ReasonCode(
                code="MISSING_SELFIE",
                message="Selfie image is required for liveness detection"
            ))
        
        # 3. ID document OCR and validation
        if "id_front" in downloaded_media:
            id_front_np = _bytes_to_numpy(downloaded_media["id_front"])
            
            # Call the production verifier
            verification_result = id_service.verify_image_array(id_front_np)
            
            if verification_result['success']:
                # Map confidence (0-1) to score (0-100)
                doc_auth_score = verification_result.get('confidence', 0.0) * 100
                
                # Extract fields from nested structure
                extracted_data = verification_result.get('verification', {}).get('extracted_data', {})
                info = extracted_data.get('info', {})
                
                doc_extracted_fields = {
                    "id_number": extracted_data.get('id_number'),
                    "birth_date": _parse_dob_from_id(extracted_data.get('id_number')),
                    "governorate": info.get('governorate'),
                    "gender": info.get('gender'),
                    "age": info.get('age'),
                    "is_valid_egyptian_id": verification_result.get('is_egyptian_id', False)
                }
                
                if not verification_result.get('is_egyptian_id', False):
                    reason_codes.append(ReasonCode(
                        code="INVALID_ID_TYPE",
                        message="Document is not a valid Egyptian ID"
                    ))
                elif doc_auth_score < 50:
                    reason_codes.append(ReasonCode(
                        code="LOW_DOC_AUTHENTICITY",
                        message="Document authenticity score too low"
                    ))
            else:
                reason_codes.append(ReasonCode(
                    code="DOC_PROCESSING_FAILED",
                    message=verification_result.get('error', 'Failed to process document')
                ))
        else:
            reason_codes.append(ReasonCode(
                code="MISSING_ID",
                message="ID front image is required"
            ))
        
        # 4. Determine suggested decision (liveness + document only, no face matching)
        suggested_decision = determine_decision(liveness_passed, doc_auth_score)
        
    except Exception as e:
        import traceback
        traceback.print_exc()
        reason_codes.append(ReasonCode(
            code="PROCESSING_ERROR",
            message=str(e)
        ))
        suggested_decision = "manual_review"
    
    return VerifyResponse(
        session_id=session_id,
        liveness_passed=liveness_passed,
        liveness_details=liveness_details,
        doc_auth_score=doc_auth_score,
        doc_extracted_fields=doc_extracted_fields,
        suggested_decision=suggested_decision,
        reason_codes=reason_codes
    )

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8100)
