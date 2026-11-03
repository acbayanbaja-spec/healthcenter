<?php
function get_analytics($pdo) {
    $c = fn($sql) => (int)$pdo->query($sql)->fetchColumn();
    $q = fn($sql) => $pdo->query($sql)->fetchAll();
    return [
        'total'    => $c('SELECT COUNT(*) FROM appointments'),
        'done'     => $c("SELECT COUNT(*) FROM appointments WHERE status='Completed'"),
        'canc'     => $c("SELECT COUNT(*) FROM appointments WHERE status='Cancelled'"),
        'patients' => $c('SELECT COUNT(*) FROM patients'),
        'doctors'  => $c('SELECT COUNT(*) FROM doctors'),
        'sets' => [
            'Appointments by status' => $q("SELECT status label, COUNT(*) n FROM appointments GROUP BY status"),
            'Appointments per doctor' => $q("SELECT d.full_name label, COUNT(a.appointment_id) n FROM doctors d LEFT JOIN appointments a USING(doctor_id) GROUP BY d.doctor_id ORDER BY n DESC"),
            'Appointments per month (last 6)' => $q("SELECT DATE_FORMAT(appt_date,'%Y-%m') label, COUNT(*) n FROM appointments GROUP BY label ORDER BY label DESC LIMIT 6"),
            'Patients by gender' => $q("SELECT gender label, COUNT(*) n FROM patients GROUP BY gender"),
            'Patients by age group' => $q("SELECT CASE WHEN TIMESTAMPDIFF(YEAR,birth_date,CURDATE())<13 THEN 'Child (0-12)' WHEN TIMESTAMPDIFF(YEAR,birth_date,CURDATE())<20 THEN 'Teen (13-19)' WHEN TIMESTAMPDIFF(YEAR,birth_date,CURDATE())<60 THEN 'Adult (20-59)' ELSE 'Senior (60+)' END label, COUNT(*) n FROM patients GROUP BY label"),
            'Top reasons for visit' => $q("SELECT reason label, COUNT(*) n FROM appointments WHERE reason<>'' GROUP BY reason ORDER BY n DESC LIMIT 5"),
        ],
    ];
}
