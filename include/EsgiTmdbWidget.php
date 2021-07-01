<?php


class EsgiTmdbWidget extends WP_Widget
{
    protected string $tmdbApiBaseUrl = "https://api.themoviedb.org/3/";
    protected string $tmdbImageUrl = "https://image.tmdb.org/t/p/w200";
    protected string $tmdbBaseUrl = "https://www.themoviedb.org/";
    protected string $language;
    protected string $region;
    protected string $tmdbKey;
    protected array $tvGenres = [];
    protected array $movieGenres = [];

    public function __construct()
    {
        parent::__construct(
            'esgi_tmdb_widget',
            'ESGI TMDB',
            ['description' => 'Widget issu du plugin ESGI TMDB']
        );
        $this->language = str_replace("_", "-", get_locale());
        $this->region = substr($this->language, 0, 2);
        $this->tmdbKey = get_option('esgi_tmdb_settings')['tmdb-key'];
        $this->esgi_get_genres('tv');
        $this->esgi_get_genres('movie');
    }

    // Front
    public function widget($args, $instance)
    {
        $types = ["movie" => (bool)$instance['movieChecked'], "tv" => (bool)$instance['tvChecked']];
        $urlArray = $this->esgi_generate_url_array($types);
        $work = $this->esgi_get_random_tmdb_item($urlArray);
        $preview = $this->esgi_display_tmdb_preview($work);

        $title = apply_filters('widget_title', $instance['title']);
        echo $before_widget . $before_title;
        echo '<h2 class="widget-title subheading heading-size-3">'.$title.'</h2>';
        echo $after_title;
        echo $preview;
        echo $after_widget;
    }

    // Back
    public function form($instance)
    {
        // Title
        $title = $instance['title'] ?? '';
        echo '
        <p>
		    <label for="'.$this->get_field_name('title').'">Titre&nbsp;:</label>
			<input class="widefat" id="'.$this->get_field_name('title').'" name="'.$this->get_field_name('title').'" type="text" value="'.$title.'">
		</p>';?>

        <!--Media selection (Movie and/or TV shows)-->
        <p>
            Média :<br>
            <input class="checkbox" type="checkbox" <?php checked( $instance['movieChecked'], 'on' ); ?> id="<?= $this->get_field_id('movieChecked'); ?>" name="<?= $this->get_field_name('movieChecked'); ?>" />
            <label for="<?= $this->get_field_id('movieChecked'); ?>">Films</label>
            <input class="checkbox" type="checkbox" <?php checked( $instance['tvChecked'], 'on' ); ?> id="<?= $this->get_field_id('tvChecked'); ?>" name="<?= $this->get_field_name('tvChecked'); ?>" />
            <label for="<?= $this->get_field_id('tvChecked'); ?>">Séries</label>
        </p>

        <!--Movie genre selection-->
        <p>
            Genres :<br>
            <?php $this->esgi_generate_movie_genres_checkboxes($instance);?>
        </p>
    <?php }

    public function update($new_instance, $old_instance): array
    {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['movieChecked'] = $new_instance['movieChecked'];
        $instance['tvChecked'] = $new_instance['tvChecked'];
        $instance['tvGenres'][] = $new_instance['tvGenres'];
        return $instance;
    }

    public function esgi_get_random_tmdb_item($urlArray){
        if($urlArray) {
            $list = [];
            foreach ($urlArray as $type => $url) {
                $responseBody = wp_remote_retrieve_body(wp_remote_get($url));
                $results = json_decode($responseBody)->results;
                foreach ($results as $index => $work) {
                    $work = (array)$work;
                    $work['type'] = $type;
                    $work = (object)$work;
                    $results[$index] = $work;
                }
                $list = array_merge($list, $results);
            }
            if(!empty($list)) return $list[rand(0, count($list) - 1)];
        }
        return false;
    }
    
    public function esgi_display_tmdb_preview($work): string
    {
        $name = $work->title ?? $work->name;
        $poster = $this->tmdbImageUrl.$work->poster_path;
        $type = $work->type == 'tv' ? "Série" : "Film";
        $url = $this->tmdbBaseUrl.$type.'/'.$work->id;

        $preview = "<a href='$url' target='_blank'><div class='esgi_tmdb_preview'>";
        $preview .= "<div class='esgi_tmdb_preview_poster'><img src='$poster'/></div>";
        $preview .= "<div class='esgi_tmdb_preview_name'>$name</div>";
        $preview .= "<div class='esgi_tmdb_preview_type'>$type</div>";
        $preview .= "</div></a>";

        return $preview;
    }

    public function esgi_get_genres($type) {
        $property = $type."Genres";
        $tmdbGenreUrl = $this->tmdbApiBaseUrl."genre/$type/list?api_key=".$this->tmdbKey."&language=$this->language";
        $responseBody = wp_remote_retrieve_body(wp_remote_get($tmdbGenreUrl));
        $tmdbGenres = json_decode($responseBody)->genres;
        foreach ($tmdbGenres as $key => $genre) {
            $this->$property[$genre->id] = $genre->name;
        }
    }

    public function esgi_generate_movie_genres_checkboxes($instance) { ?>
        <?php foreach ($this->movieGenres as $id => $name) { ?>
            <input class="checkbox" type="checkbox" id="<?= $this->get_field_id('genre_'.$id) ?>" name="tvGenres[]" <?php checked($instance['genre_'.$id], 'on') ?> />
            <label for="<?= $this->get_field_id('genre_'.$id) ?>"><?= $name ?></label>
        <?php }
    }

    public function esgi_generate_url_array($types): array
    {
        $urlArray = [];
        foreach ($types as $type => $activated) {
            if($activated)
                $urlArray[$type] = $this->tmdbApiBaseUrl."discover/$type?api_key=".$this->tmdbKey."&language=".$this->language."&region=".$this->region."&sort_by=popularity.desc&include_adult=false&include_video=false&page=1";
        }
        return $urlArray;
    }

}