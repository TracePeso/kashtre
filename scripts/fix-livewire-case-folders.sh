#!/usr/bin/env bash
# Ensures Livewire component directories use PascalCase (PSR-4) on case-sensitive filesystems.
set -euo pipefail

ROOT="${1:-.}"
LW="${ROOT}/app/Livewire"

if [ ! -d "$LW" ]; then
  echo "Livewire path not found: $LW"
  exit 0
fi

renames=(
  "suppliers:Suppliers"
  "stores:Stores"
  "itemUnits:ItemUnits"
  "departments:Departments"
  "groups:Groups"
  "titles:Titles"
  "roles:Roles"
  "sections:Sections"
  "rooms:Rooms"
  "qualifications:Qualifications"
  "subGroups:SubGroups"
  "patientCategory:PatientCategory"
  "transactions:Transactions"
)

for pair in "${renames[@]}"; do
  src="${pair%%:*}"
  dst="${pair##*:}"

  if [ -d "$LW/$src" ] && [ "$src" != "$dst" ]; then
    tmp="$LW/.rename_${dst}_$$"
    echo "Renaming Livewire folder: $src -> $dst"
    mv "$LW/$src" "$tmp"
    mv "$tmp" "$LW/$dst"
  fi
done

echo "Livewire folder casing check complete."
