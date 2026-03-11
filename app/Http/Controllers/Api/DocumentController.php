<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Document;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Carbon\Carbon;

class DocumentController extends Controller
{
    /**
     * Afficher la liste des documents avec recherche et filtres
     */
    public function index(Request $request)
    {
        try {
            $query = Document::with(['category', 'user', 'tags']);
            
            // RECHERCHE PAR MOT-CLÉ (dans titre et description)
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('title', 'like', "%{$searchTerm}%")
                      ->orWhere('description', 'like', "%{$searchTerm}%");
                });
            }
            
            // FILTRE PAR CATÉGORIE
            if ($request->has('category_id') && !empty($request->category_id)) {
                $query->where('category_id', $request->category_id);
            }
            
            // FILTRE PAR TAGS (multiple)
            if ($request->has('tags') && !empty($request->tags)) {
                $tagIds = is_array($request->tags) ? $request->tags : explode(',', $request->tags);
                $query->whereHas('tags', function($q) use ($tagIds) {
                    $q->whereIn('tags.id', $tagIds);
                });
            }
            
            // FILTRE PAR AUTEUR
            if ($request->has('author_id') && !empty($request->author_id)) {
                $query->where('user_id', $request->author_id);
            }
             // FILTRE PAR DATE (un seul jour)
            if ($request->has('date') && !empty($request->date)) {
                $query->whereDate('created_at', $request->date);
            }  
            
            //solution pour filtrage de dat par un seul jr de 00 a 23h 59
                // FILTRE PAR DATE (un seul jour)
            // if ($request->has('date') && !empty($request->date)) {
            //     $date = Carbon::parse($request->date,'Europe/Paris');
            //     $query->whereBetween('created_at', [
            //         $date->startOfDay()->toDateTimeString(),  // 2024-03-06 00:00:00
            //         $date->endOfDay()->toDateTimeString()     // 2024-03-06 23:59:59
            //     ]);
            // }   
            // FILTRE PAR TYPE DE FICHIER
            if ($request->has('file_type') && !empty($request->file_type)) {
                $query->where('file_type', $request->file_type);
            }
            
            // FILTRE PAR STATUT
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }
            
            // TRI
            $sortField = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            $allowedSortFields = ['created_at', 'title', 'file_size', 'download_count', 'view_count'];
            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortOrder);
            }
            
            // Pagination
            $perPage = $request->get('per_page', 15);
            $documents = $query->paginate($perPage);
            
            // Transformer les documents pour s'assurer que user_id est présent
            $documents->getCollection()->transform(function ($doc) {
                return [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'description' => $doc->description,
                    'file_name' => $doc->file_name,
                    'file_path' => $doc->file_path,
                    'file_type' => $doc->file_type,
                    'file_size' => $doc->file_size,
                    'mime_type' => $doc->mime_type,
                    'category_id' => $doc->category_id,
                    'user_id' => $doc->user_id,
                    'status' => $doc->status,
                    'view_count' => $doc->view_count,
                    'download_count' => $doc->download_count,
                    'created_at' => $doc->created_at,
                    'updated_at' => $doc->updated_at,
                    'category' => $doc->category,
                    'user' => $doc->user,
                    'tags' => $doc->tags,
                ];
            });
            
            Log::info('Recherche documents', [
                'filters' => $request->all(),
                'total' => $documents->total()
            ]);
            
            return response()->json([
                'data' => $documents->items(),
                'meta' => [
                    'current_page' => $documents->currentPage(),
                    'last_page' => $documents->lastPage(),
                    'per_page' => $documents->perPage(),
                    'total' => $documents->total()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur recherche documents', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Uploader un nouveau document
     */
    public function store(Request $request)
    {
        Log::info('=== DÉBUT UPLOAD DOCUMENT ===');
        Log::info('Données reçues:', [
            'title' => $request->title,
            'category_id' => $request->category_id,
            'has_file' => $request->hasFile('file'),
            'tags' => $request->tags,
            'all_input' => $request->all()
        ]);
        
        try {
            // Validation
            Log::info('Étape 1: Validation');
            $request->validate([
                'title' => 'required|string|max:255',
                'category_id' => 'required|exists:categories,id',
                'file' => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,txt',
                'description' => 'nullable|string',
                'tags' => 'nullable|array'
            ]);
            Log::info('✅ Validation OK');

            // Fichier
            Log::info('Étape 2: Traitement du fichier');
            $file = $request->file('file');
            Log::info('Fichier:', [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType()
            ]);

            $fileName = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('documents', $fileName, 'public');
            Log::info('✅ Fichier sauvegardé:', ['path' => $filePath]);

            // Utilisateur
            Log::info('Étape 3: Récupération utilisateur');
            $userId = auth()->id();
            if (!$userId) {
                Log::info('Utilisateur non connecté, utilisation du premier utilisateur');
                $user = \App\Models\User::first();
                if (!$user) {
                    throw new \Exception('Aucun utilisateur trouvé dans la base');
                }
                $userId = $user->id;
            }
            Log::info('✅ Utilisateur ID:', ['user_id' => $userId]);

            // Création document
            Log::info('Étape 4: Création du document');
            $document = Document::create([
                'title' => $request->title,
                'description' => $request->description,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'category_id' => $request->category_id,
                'user_id' => $userId,
                'status' => 'active'
            ]);
            Log::info('✅ Document créé:', ['id' => $document->id]);

            // Tags
            Log::info('Étape 5: Traitement des tags');
            if ($request->has('tags')) {
                $tags = $request->tags;
                Log::info('Tags reçus:', ['tags' => $tags]);
                $document->tags()->sync($tags);
                foreach ($tags as $tagId) {
                    Tag::find($tagId)?->increment('usage_count');
                }
                Log::info('✅ Tags attachés');
            }

            Log::info('=== UPLOAD RÉUSSI ===');
            return response()->json([
                'message' => 'Document uploadé avec succès',
                'document' => $document->load(['category', 'user', 'tags'])
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Erreur validation:', ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('❌ Erreur upload document:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Erreur lors de l\'upload',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un document spécifique
     */
    public function show(Document $document)
    {
        // Incrémenter le compteur de vues
        $document->increment('view_count');
        
        // Charger les relations
        $document->load(['user', 'category', 'tags']);
        
        // Retourner avec user_id explicite
        return response()->json([ 
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'description' => $document->description,
                'file_name' => $document->file_name,
                'file_path' => $document->file_path,
                'file_type' => $document->file_type,
                'file_size' => $document->file_size,
                'mime_type' => $document->mime_type,
                'category_id' => $document->category_id,
                'user_id' => $document->user_id,
                'status' => $document->status,
                'view_count' => $document->view_count,
                'download_count' => $document->download_count,
                'created_at' => $document->created_at,
                'updated_at' => $document->updated_at,
                'user' => $document->user,
                'category' => $document->category,
                'tags' => $document->tags,
            ]
        ]);
    }
    // pour afficher le datail d un document
    public function Docshow(Document $document)
    {
        // Charger les relations nécessaires
        $document->load(['user', 'category', 'tags']);
        
        // Incrémenter le compteur de vues
        $document->increment('view_count');
        
        // Journaliser la consultation (pour historique)
        Log::info('Consultation document', [
            'user_id' => Auth::id(),
            'document_id' => $document->id,
            'action' => 'view'
        ]);
        
        return response()->json([
            'document' => $document
        ]);
    }
    // historique des acces du document
    /**
 * Récupérer l'historique des accès d'un document
 */
    public function history(Document $document)
    {
        $user = Auth::user();
        
        // Vérifier que l'utilisateur est connecté - tous les utilisateurs peuvent voir l'historique
        if (!$user) {
            return response()->json([
                'message' => 'Accès non autorisé'
            ], 403);
        }
        
        // Log de la consultation (pour l'historique justement)
        Log::info('Consultation historique document', [
            'user_id' => $user->id,
            'document_id' => $document->id,
            'action' => 'view_history'
        ]);
        
        // Charger l'utilisateur du document
        $document->load('user');
        
        // Simuler un historique (à remplacer par votre table d'historique réelle)
        $history = [
            [
                'action' => 'upload',
                'user_name' => $document->user->name ?? 'Unknown',
                'user_id' => $document->user_id,
                'created_at' => $document->created_at,
                'description' => 'Document uploadé'
            ],
            [
                'action' => 'view',
                'user_name' => $user->name,
                'user_id' => $user->id,
                'created_at' => now(),
                'description' => 'Consultation de l\'historique'
            ]
        ];
        
        return response()->json([
            'history' => $history
        ]);
    }

        /**
     * Afficher la page d'édition d'un document
     */
    public function edit($id)
    {
        try {
            // Récupérer le document avec ses relations
            $document = Document::with(['category', 'tags'])->findOrFail($id);
            
            // Récupérer les catégories et tags pour les selects
            $categories = Category::all();
            $tags = Tag::all();
            
            // Rendre la vue Inertia avec les données
            return Inertia::render('Documents/Edit', [
                'document' => $document,
                'categories' => $categories,
                'tags' => $tags,
                'id' => $id
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur chargement page edit:', ['error' => $e->getMessage()]);
            return redirect()->route('documents.index')->with('error', 'Document non trouvé');
        }
    }

    /**
     * Mettre à jour un document
     */
    public function update(Request $request, Document $document)
    {
        $user = auth()->user();
    
        // Vérifier les permissions
        if (!$user->hasRole('admin') && $document->user_id !== $user->id) {
            return response()->json([
                'message' => 'Vous ne pouvez modifier que vos propres documents'
            ], 403);
        }

        // Validation
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'status' => 'sometimes|in:active,archived,draft',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
            'user_id' => 'nullable|exists:users,id'
        ]);

        try {
            // Mettre à jour les champs basiques
            $updateData = $request->only(['title', 'description', 'category_id', 'status']);
            
            // Ajouter user_id si l'admin le change
            if ($user->hasRole('admin') && $request->has('user_id') && !empty($request->user_id)) {
                $updateData['user_id'] = $request->user_id;
            }
            
            $document->update($updateData);

            // Mettre à jour les tags
            if ($request->has('tags')) {
                $tags = $request->tags ?? [];
                
                // Diminuer le usage_count des anciens tags
                foreach ($document->tags as $oldTag) {
                    if (!in_array($oldTag->id, $tags)) {
                        $oldTag->decrement('usage_count');
                    }
                }
                
                // Augmenter le usage_count des nouveaux tags
                foreach ($tags as $tagId) {
                    if (!$document->tags->pluck('id')->contains($tagId)) {
                        Tag::find($tagId)?->increment('usage_count');
                    }
                }
                
                // Synchroniser les tags
                $document->tags()->sync($tags);
            }

            Log::info('Document mis à jour', ['document_id' => $document->id, 'user_id' => $user->id]);

            // Retourner une réponse JSON pour redirection côté client
            return response()->json([
                'message' => 'Document mis à jour avec succès',
                'redirect' => '/documents'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour document:', [
                'message' => $e->getMessage(),
                'document_id' => $document->id
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Télécharger un document
     */
    public function download(Document $document)
    {
        try {
            // Incrémenter le compteur de téléchargements
            $document->increment('download_count');
            
            // Retourner l'URL de téléchargement
            return response()->json([
                'download_url' => Storage::url($document->file_path)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur téléchargement document:', [
                'message' => $e->getMessage(),
                'document_id' => $document->id
            ]);
            
            return response()->json([
                'message' => 'Erreur lors du téléchargement',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}