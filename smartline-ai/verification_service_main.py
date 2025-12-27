

from fastapi import FastAPI, HTTPException, Header, Depends
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Dict, List, Optional, Any
import httpx
import os
from dotenv import load_dotenv

load_dotenv()

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

# Configuration
API_KEY = os.getenv("FASTAPI_VERIFICATION_KEY", "your-secret-key")


# ... imports ...
import cv2
import numpy as np
import face_recognition
import io
from production_egyptian_id_verifier_enhanced import IDVerificationService

# Initialize ID Verification Service (loads OCR models)
print("Initializing ID Verification Service...")
id_service = IDVerificationService()
print("ID Verification Service initialized.")

# ... existing code ...

@app.post("/internal/verify", response_model=VerifyResponse)
async def verify_documents(
    request: VerifyRequest,
    api_key: str = Depends(verify_api_key)
):
    """
    Main verification endpoint.
    Downloads media, checks quality, performs OCR/ID verification, and matches faces.
    """
    session_id = request.session_id
    media = request.media
    
    reason_codes = []
    
    # Initialize response values
    liveness_quality = True
    liveness_quality_details = {}
    doc_auth_score = 0.0
    doc_extracted_fields = {}
    face_match_score = 0.0
    
    try:
        # 1. Download media files
        downloaded_media = await download_media(media)
        
        # 2. Image quality checks
        if "selfie" in downloaded_media:
            selfie_np = _bytes_to_numpy(downloaded_media["selfie"])
            quality_result = check_image_quality(selfie_np)
            liveness_quality = quality_result["passed"]
            liveness_quality_details = quality_result
            if not liveness_quality:
                reason_codes.append(ReasonCode(
                    code="LOW_QUALITY_SELFIE",
                    message=quality_result.get("message", "Selfie quality too low")
                ))
        
        # 3. ID document OCR and validation (using existing enhanced verifier)
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
                    "name": "Detected", # Name OCR is hard, usually we just detect presence
                    "birth_date": _parse_dob_from_id(extracted_data.get('id_number')),
                    "governorate": info.get('governorate'),
                    "gender": info.get('gender'),
                    "age": info.get('age')
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

        # 4. Face matching (Selfie vs ID Front)
        if "selfie" in downloaded_media and "id_front" in downloaded_media:
            # We need to extract the face from the ID card first
            # The verifier might have already extracted faces, but let's do fresh detection
            # or use the full images with face_recognition library
            
            face_match_score = compare_faces(
                _bytes_to_numpy(downloaded_media["selfie"]),
                _bytes_to_numpy(downloaded_media["id_front"])
            )
            
            if face_match_score < 60:
                reason_codes.append(ReasonCode(
                    code="FACE_MISMATCH",
                    message="Face in selfie doesn't match ID photo"
                ))
        
        # 5. Determine suggested decision
        suggested_decision = determine_decision(
            liveness_quality,
            face_match_score,
            doc_auth_score
        )
        
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
        liveness_quality=liveness_quality,
        liveness_quality_details=liveness_quality_details,
        doc_auth_score=doc_auth_score,
        doc_extracted_fields=doc_extracted_fields,
        face_match_score=face_match_score,
        suggested_decision=suggested_decision,
        reason_codes=reason_codes
    )

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

def check_image_quality(image: np.ndarray) -> Dict[str, Any]:
    """Check image quality using OpenCV."""
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    
    # Check blur (Laplacian variance)
    blur_score = cv2.Laplacian(gray, cv2.CV_64F).var()
    is_blurry = blur_score < 100.0  # Threshold can be adjusted
    
    # Check brightness
    avg_brightness = np.mean(gray)
    is_dark = avg_brightness < 40
    is_overexposed = avg_brightness > 220
    
    passed = not (is_blurry or is_dark or is_overexposed)
    
    message = "Quality checks passed"
    if is_blurry: message = "Image is too blurry"
    elif is_dark: message = "Image is too dark"
    elif is_overexposed: message = "Image is overexposed"
    
    return {
        "passed": passed,
        "blur_score": float(blur_score),
        "brightness_score": float(avg_brightness),
        "message": message
    }

def compare_faces(selfie_img: np.ndarray, id_img: np.ndarray) -> float:
    """
    Compare faces using face_recognition library.
    Returns match score 0-100.
    """
    try:
        # Convert BGR (OpenCV) to RGB (face_recognition)
        selfie_rgb = cv2.cvtColor(selfie_img, cv2.COLOR_BGR2RGB)
        id_rgb = cv2.cvtColor(id_img, cv2.COLOR_BGR2RGB)
        
        # Detect faces
        selfie_encodings = face_recognition.face_encodings(selfie_rgb)
        id_encodings = face_recognition.face_encodings(id_rgb)
        
        if not selfie_encodings:
            print("No face found in selfie")
            return 0.0
        
        if not id_encodings:
            print("No face found in ID")
            return 0.0
            
        # Compare the first found face in each image
        # face_distance returns distance (lower is better match)
        # Typically < 0.6 is a match
        distance = face_recognition.face_distance([selfie_encodings[0]], id_encodings[0])[0]
        
        # Convert distance to similarity score (0-100)
        # 0.0 distance => 100% match
        # 1.0 distance => 0% match (although usually distances are < 1.0)
        score = max(0.0, (1.0 - distance) * 100.0)
        
        return float(score)
        
    except Exception as e:
        print(f"Face comparison error: {e}")
        return 0.0

# ... rest of file ...
    selfie: Optional[str] = None
    liveness_video: Optional[str] = None
    id_front: Optional[str] = None
    id_back: Optional[str] = None


class VerifyRequest(BaseModel):
    session_id: str
    media: Dict[str, str]  # {kind: signed_url}


class ReasonCode(BaseModel):
    code: str
    message: str


class VerifyResponse(BaseModel):
    session_id: str
    liveness_quality: bool
    liveness_quality_details: Dict[str, Any]
    doc_auth_score: float
    doc_extracted_fields: Dict[str, Any]
    face_match_score: float
    suggested_decision: str
    reason_codes: List[ReasonCode]


# ==================== Dependencies ====================

async def verify_api_key(x_api_key: str = Header(...)):
    """Verify the API key from request header."""
    if x_api_key != API_KEY:
        raise HTTPException(status_code=401, detail="Invalid API key")
    return x_api_key


# ==================== Endpoints ====================

@app.get("/health")
async def health_check():
    """Health check endpoint."""
    return {"status": "healthy", "service": "verification"}

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

def determine_decision(liveness_quality: bool, face_match: float, doc_auth: float) -> str:
    """Determine suggested decision based on scores."""
    if not liveness_quality:
        return "rejected"
    
    if face_match >= 85 and doc_auth >= 75:
        return "approved"
    elif face_match >= 60 and doc_auth >= 50:
        return "manual_review"
    else:
        return "rejected"


# ==================== Run ====================

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8100)
