-- stable-study.lua
-- Orthanc Lua callback: notify the KashTre RIS when a study settles (all images received).
-- Register in Orthanc's configuration:  "LuaScripts": [ "C:\\Orthanc\\stable-study.lua" ]
-- Fires StableAge seconds after the last instance arrives (set "StableAge" in config).

-- Local dev: Orthanc and XAMPP run on the same PC. Point this at your Laravel app's URL.
-- If your app is served from an XAMPP vhost/subfolder, include the full path, e.g.
--   http://127.0.0.1/kashtre/public/api/orthanc/stable-study
local RIS_WEBHOOK_URL = 'http://127.0.0.1/api/orthanc/stable-study'
local SHARED_SECRET   = 'b03706de01580abca77a903dbbdcfe799f619844558b83bd42b4fd9aa80c7cd6'


function OnStableStudy(studyId, tags, metadata)
   -- Only the Orthanc study id is trusted downstream; the RIS re-fetches authoritative
   -- tags from Orthanc's REST API. This payload is a trigger, not a source of truth.
   local payload = {
      event            = 'STABLE_STUDY',
      orthancStudyId   = studyId,
      accessionNumber  = tags['AccessionNumber'] or '',
      studyInstanceUid = tags['StudyInstanceUID'] or '',
      secret           = SHARED_SECRET
   }

   local ok, err = pcall(function()
      HttpPost(RIS_WEBHOOK_URL, DumpJson(payload, true))
   end)

   if not ok then
      -- Orthanc keeps the study; a polling reconciler or manual retry can pick it up.
      print('RIS StableStudy webhook failed for accession ' ..
            (tags['AccessionNumber'] or '?') .. ': ' .. tostring(err))
   end
end
