#!/usr/bin/env python3
"""Shared loader for the CBC competency levels defined in outcome-levels.yaml.

One source of truth so gen_coverage.py and check_competency_descriptors.py can't drift
apart on the vocabulary. Kept out of competencies.yaml deliberately: that file is a flat
`category -> [names]` map and every script flattens its `.values()`, so a level block
living there would silently register as five extra competencies.
"""
import pathlib
import sys

try:
    import yaml
except ImportError:  # pragma: no cover - same guard the callers use
    sys.exit("pyyaml required: pip install pyyaml")

REPO = pathlib.Path(__file__).resolve().parent.parent
LEVELS_YAML = REPO / "outcome-levels.yaml"


def load():
    """Return (ladder_labels, course_target_labels, legacy_to_label).

    ladder_labels        — ordered 0..4, the values a descriptor's `outcome_levels:` may use.
    course_target_labels — the subset a module's `target_outcome_level:` may use (1..4).
    legacy_to_label      — old rung name -> canonical label, for error messages.
    """
    data = yaml.safe_load(LEVELS_YAML.read_text(encoding="utf-8"))
    levels = data["levels"]
    by_id = {lv["id"]: lv["label"] for lv in levels}
    ladder = [lv["label"] for lv in levels]
    targets = [by_id[i] for i in data["course_target_levels"]]
    legacy = {lv["legacy"]: lv["label"] for lv in levels}
    # the four-rung ladders name level 3 "Trainer"; five-rung ones "Trainer/Proficient"
    legacy.setdefault("Trainer", by_id[3])
    return ladder, targets, legacy
