<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceApplicationDocumentModel extends Model
{
    protected $table            = 'service_application_documents';
    protected $primaryKey       = 'document_id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'application_id',
        'document_type',
        'filename',
        'original_name',
        'mime_type',
        'path',
        'uploaded_by',
    ];
}
