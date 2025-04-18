<?php

namespace App\services;

use App\Exceptions\FilterException;
use App\Exceptions\NotFoundException;
use App\Utils\Response;
use Exception;
use Illuminate\Support\Facades\DB;

class MainService {

    public function create($data){
        try {
            DB::beginTransaction();
        
           
            $filename = date('YmdH') . $data['image']->getClientOriginalName();
            $data['image']->move(public_path('uploads'), $filename);
            $data['image'] =   'projects/bibli-digital/public/uploads/' . $filename;

            $data['author_id'] = $this->getAuthorId($data['author_id']);

            if(!DB::table('books')->insert($data)) 
                throw new Exception();
            
            DB::commit();
            return Response::getResponse(true, '');

        } catch (Exception $e) {
            DB::rollBack();
            error_log(json_encode($e));
            return Response::getResponse(false, "Falha ao cadastrar livro");
        }
    }

    private function getAuthorId($name) {

        $author = DB::table('authors')
            ->whereLike("name", "_" . "$name" . "_")
            ->first();
        
        if(empty($author)) {

            DB::table('authors')->insert(['name' => $name]);

            $author = DB::table('authors')
                ->whereLike("name", "%$name%")
                ->first();
        }

        return $author->id;
    }

    public function searchAllCategories(){

        return DB::table("categories")->get()->toArray();
    }

    public function searchAllYears(){

        return DB::table("books")
                ->groupBy("year")
                ->orderBy("year", "desc")
                ->get("year")
                ->toArray();
    }

    public function searchWithFilters($filters): Response
    {

        try {

            if(!empty($filters['input'])) 
                return Response::getResponse(true, '', $this->searchByInput($filters['input']));
            
            return Response::getResponse(true, '', $this->filterHome($filters['category'], $filters['year']));
        } catch (FilterException $e) {
            return Response::getResponse(false, $e->getMessage());
        }
    }

    private function searchByInput($input){
        
        try {

            $books = DB::table('books')
                ->join("authors", "author_id", "=", "authors.id")
                ->whereLike("title", "%$input%")
                ->get()
                ->toArray();

            if(empty($books)) 
                throw new NotFoundException("Não existe livros com este título!");
                
            return $books;

        } catch (NotFoundException $e) {
            throw new FilterException($e->getMessage());
        }   catch (Exception $e) {
            throw new FilterException($e->getMessage());
        }
    }

    public function filterHome($category_id, $year)
    {

        try {

            if($category_id != 0  || $year != 0) 
                return $this->filter($year, $category_id);
           
            return DB::table("books")
                ->join("authors", "author_id", "=", "authors.id")
                ->get()
                ->toArray();

        }  catch (NotFoundException $e) {
            throw new FilterException($e->getMessage());
        }   catch (Exception $e) {
            throw new FilterException($e->getMessage());
        }
    }

    private function filter($year, $category_id) {

        if($category_id == 0) 
            return $this->filterOnlyYear($year);
        
        if($year == 0) 
            return $this->filterOnlyCategory($category_id);
            
        return $this->filterYearAndCategory($year,$category_id);
    }

    private function filterOnlyYear($year){

        $books = DB::table("books")
            ->join("authors", "author_id", "=", "authors.id")
            ->where("books.year", $year)
            ->get()
            ->toArray();

        if(empty($books))
            throw new NotFoundException("Este ano não possui livros lançados!");

        return $books;
    }

    private function filterOnlyCategory($category_id) {

        $books = DB::table("books")
                    ->join("authors", "author_id", "=", "authors.id")
                    ->where("books.category_id", $category_id)
                    ->get()
                    ->toArray();

        if(empty($books))
            throw new NotFoundException("Esta categoria não possui livros!");

        return $books;
    }

    private function filterYearAndCategory($year, $category_id){

        $books = DB::table("books")
                    ->join("authors", "author_id", "=", "authors.id")
                    ->where("books.year", $year)
                    ->where("books.category_id", $category_id)
                    ->get()
                    ->toArray();

        if(empty($books)) 
            throw new NotFoundException();

        return $books;
    }
}