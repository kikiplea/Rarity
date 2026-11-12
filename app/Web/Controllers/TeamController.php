<?php

declare(strict_types=1);

namespace App\Web\Controllers;
use App\DTO\TeamDTO;
use App\DTO\TeamInputDTO;
use App\Models\Team;
use Core\Controller;
use Core\Request;
use Core\ValidationException;

final class TeamController extends Controller
{
    public function index(Request $request): void
    {
        $teams = Team::query(
            '
            SELECT teams.id, teams.name, COUNT(userTeam.id) as members_count 
            FROM teams LEFT JOIN userTeam ON teams.id = userTeam.team_id
            GROUP BY teams.id, teams.name
            ORDER BY teams.name ASC
             '
        );
        $this->view('teams/index.twig', ['teams' => $teams]);
    }

    public function create(Request $request): void
    {
        $this->view('teams/add.twig', [
            'action' => $this->view->url('/teams'),
        ]);
    }

    public function store(Request $request):void
    {
        try {
            $input = TeamInputDTO::fromArray($request->all());
        } catch (ValidationException $e) {
            $this->session->flash('errors', $e->errors);
            $this->session->flash('old', $request->all());
            $this->redirect('/teams/create');
        } 
        $name = trim($input->name);
        if($name === '') {
            $this->session->flash('errors', ['name' => 'Le nom de l\'équipe est obligatoire.']);
            $this->session->flash('old', $request->all());
            $this->redirect('/teams/create');
        }
        $now = date('Y-m-d H:i:s');
        $id =Team::insert([
            'name' => $name,
            'created_at' => $now,
            'updated_at' => $now
        ]);
       
        $this->session->flash('success', 'Equipe crée');
        $this->redirect('/teams'.$id);

    }

    public function show(Request $request): void
    {
        $team = Team::find((int) $request->param('id'));
        if ($team=== null) {
            $this->session->flash('error', 'Equipe introuvable');
            $this->redirect('/teams');
        }
        $teamId=(int) $team['id'];
        $this->view('teams/show.twig', [
            'team' => $team,
            'members' => $this->membersForTeam($teamId),
            'available_users' => $this->availableUsersForTeam($teamId),
        ]);
    }

    public function addMember(Request $request): void
    {
        $teamId = (int) $request->param('id');
    if(Team::find($teamId) === null) {
        $this->session->flash('error', 'Equipe introuvable');
        $this->redirect('/teams');
    }

    $userId = (int) $request->input('user_id', 0);
    if($userId === 0 || User::find($userId) === null) {
        $this->session->flash('error', 'Utilisateur introuvable');
        $this->redirect('/teams/'.$teamId);
    }

    $exists=UserTeam::table()
    ->where('team_id', $teamId)   
    ->where('user_id', $userId)
    ->first();

    if($exists !== null) {
        $this->session->flash('error', 'Lutilisateur est deja dans l\'équipe');
        $this->redirect('/teams/'.$teamId);
    }
    $now=time();
    UserTeam::insert([
        'team_id' => $teamId,
        'user_id' => $userId,
        'created_at' =>  $now,
        'updated_at' =>$now,
    ]);

    $this->session->flash('success', 'Utilisateur ajouté à l\'équipe');
    $this->redirect('/teams/'.$teamId);
    }

    public function removeMember(Request $request): void
    {
        $teamId = (int) $request->param('id');
        if(Team::find($teamId) === null) {
            $this->session->flash('error', 'Equipe introuvable');
            $this->redirect('/teams');
        }

        $userId = (int) $request->input('user_id', 0);
        if($userId === 0 || User::find($userId) === null) {
            $this->session->flash('error', 'Utilisateur introuvable');
            $this->redirect('/teams/'.$teamId);
        }

        UserTeam::table()
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->delete();

        $this->session->flash('success', 'Utilisateur retiré de l\'équipe');
        $this->redirect('/teams/'.$teamId);
    }
}