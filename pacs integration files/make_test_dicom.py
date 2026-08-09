# Generates a synthetic DICOM instance for manually testing the PACS
# integration without a real scanner or DCMTK. Upload the result straight to
# Orthanc's REST API (or via Explorer 2's Upload button) to simulate a
# modality sending an acquired image.
#
# Every clinically-relevant field is a REQUIRED named argument — there are
# no hardcoded patient/modality defaults. An earlier version of this script
# hardcoded PatientName="Test^PACS" and Modality="CT" regardless of what
# study you were actually testing against, which meant Orthanc would show
# the wrong patient and modality for a real study (e.g. an X-Ray order
# showing up as a CT scan on a made-up patient) — exactly the kind of
# inconsistency that's dangerous to have lying around even in a test
# system. Pull the real values from the study you're testing (see the
# tinker snippet in pacs integration files/END-TO-END-WEB-TEST.md) rather
# than typing something plausible-looking.
#
# Usage:
#   pip install pydicom   (one-time)
#   python make_test_dicom.py \
#     --accession ACC-2026-000004 \
#     --study-uid 2.25.5113185261336863089411055342318921347 \
#     --patient-name "Maganda Jalia" \
#     --patient-id EXKTHGYC \
#     --modality DX \
#     --physician "Martin Mugenyi" \
#     --output test.dcm
#
#   curl.exe -u kashtre:kashtre -X POST http://127.0.0.1:8042/instances --data-binary "@test.dcm"
#
# --modality must be a real DICOM code (DX, CT, MR, US, MG, XA, ...), not
# this app's internal modality_type vocabulary — see the mapping table in
# App\Services\Imaging\OrthancDicomWorklistBroker::DICOM_MODALITY_CODES.

import argparse
import datetime
import sys

import pydicom
from pydicom.dataset import FileDataset, FileMetaDataset
from pydicom.uid import ExplicitVRLittleEndian, generate_uid

parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
parser.add_argument("--accession", required=True, help="Must match the real ImagingStudy.accession_number")
parser.add_argument("--study-uid", required=True, help="Must match the real ImagingStudy.study_instance_uid")
parser.add_argument("--patient-name", required=True, help="Real client full_name — not a placeholder")
parser.add_argument("--patient-id", required=True, help="Real Client.client_id")
parser.add_argument("--modality", required=True, help="Real DICOM code matching the protocol, e.g. DX/CT/MR/US/MG/XA")
parser.add_argument("--physician", default="", help="Ordering/referring physician name, if known")
parser.add_argument("--output", required=True, help="Output .dcm file path")
args = parser.parse_args()

file_meta = FileMetaDataset()
file_meta.MediaStorageSOPClassUID = "1.2.840.10008.5.1.4.1.1.7"  # Secondary Capture
file_meta.MediaStorageSOPInstanceUID = generate_uid()
file_meta.TransferSyntaxUID = ExplicitVRLittleEndian

ds = FileDataset(args.output, {}, file_meta=file_meta, preamble=b"\0" * 128)
ds.is_little_endian = True
ds.is_implicit_VR = False

now = datetime.datetime.now()
ds.SOPClassUID = file_meta.MediaStorageSOPClassUID
ds.SOPInstanceUID = file_meta.MediaStorageSOPInstanceUID
ds.StudyInstanceUID = args.study_uid
ds.SeriesInstanceUID = generate_uid()
ds.AccessionNumber = args.accession
ds.PatientName = args.patient_name
ds.PatientID = args.patient_id
ds.Modality = args.modality
ds.ReferringPhysicianName = args.physician
ds.RequestingPhysician = args.physician
ds.StudyDate = now.strftime("%Y%m%d")
ds.StudyTime = now.strftime("%H%M%S")
ds.SeriesNumber = "1"
ds.InstanceNumber = "1"

# A visible synthetic pattern (radial gradient + grid), not a blank image —
# purely so it doesn't look broken in a viewer. Still obviously not a real
# scan; the point of this test is the workflow (worklist -> C-STORE ->
# stable -> webhook), not the pixel content. Pure Python, no numpy/PIL, to
# keep the only dependency pydicom.
SIZE = 128
CENTER = SIZE / 2
pixels = bytearray(SIZE * SIZE)
for y in range(SIZE):
    for x in range(SIZE):
        dist = ((x - CENTER) ** 2 + (y - CENTER) ** 2) ** 0.5
        shade = max(0, 255 - int(dist * 3))
        if x % 16 == 0 or y % 16 == 0:
            shade = 255
        pixels[y * SIZE + x] = shade

ds.Rows = SIZE
ds.Columns = SIZE
ds.BitsAllocated = 8
ds.BitsStored = 8
ds.HighBit = 7
ds.PixelRepresentation = 0
ds.SamplesPerPixel = 1
ds.PhotometricInterpretation = "MONOCHROME2"
ds.PixelData = bytes(pixels)

ds.save_as(args.output, enforce_file_format=True)
print("wrote", args.output)
