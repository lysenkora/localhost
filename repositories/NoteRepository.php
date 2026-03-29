<?php
// ============================================================================
// РЕПОЗИТОРИЙ ЗАМЕТОК
// ============================================================================

class NoteRepository {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Получение всех заметок
     */
    public function getAll($includeArchived = false, $limit = null) {
        $sql = "SELECT * FROM notes WHERE 1=1";
        
        if (!$includeArchived) {
            $sql .= " AND is_archived = 0";
        } else if ($includeArchived === 'archived') {
            $sql .= " AND is_archived = 1";
        }
        
        $sql .= " ORDER BY 
                    CASE note_type
                        WHEN 'important' THEN 1
                        WHEN 'reminder' THEN 2
                        WHEN 'idea' THEN 3
                        ELSE 4
                    END,
                    created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->pdo->query($sql)->fetchAll();
    }
    
    /**
     * Получение заметки по ID
     */
    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM notes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Добавление заметки
     */
    public function create($title, $content, $type = 'general', $reminderDate = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO notes (title, content, note_type, reminder_date, is_archived, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$title, $content, $type, $reminderDate]);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Обновление заметки
     */
    public function update($id, $title, $content, $type, $reminderDate = null) {
        $stmt = $this->pdo->prepare("
            UPDATE notes 
            SET title = ?, content = ?, note_type = ?, reminder_date = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$title, $content, $type, $reminderDate, $id]);
    }
    
    /**
     * Удаление заметки
     */
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM notes WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Архивация/восстановление заметки
     */
    public function archive($id, $archive = true) {
        $stmt = $this->pdo->prepare("UPDATE notes SET is_archived = ? WHERE id = ?");
        return $stmt->execute([$archive ? 1 : 0, $id]);
    }
}