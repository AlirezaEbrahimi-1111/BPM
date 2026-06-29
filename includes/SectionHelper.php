<?php
function getSectionLabel($db, $org_id, $section_key) {
    // management و supervisor رزرو شده‌ان
    $fixed = ['management' => 'مدیریت', 'supervisor' => 'سرپرست'];
    if (isset($fixed[$section_key])) return $fixed[$section_key];

    $stmt = $db->prepare("
        SELECT section_label FROM organization_activity_sections 
        WHERE organization_id = ? AND section_key = ?
    ");
    $stmt->execute([$org_id, $section_key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['section_label'] : $section_key;
}

function getSectionsList($db, $org_id) {
    $stmt = $db->prepare("
        SELECT section_key, section_label FROM organization_activity_sections 
        WHERE organization_id = ? AND is_active = 1 ORDER BY sort_order
    ");
    $stmt->execute([$org_id]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['sales' => 'فروش', ...]
}