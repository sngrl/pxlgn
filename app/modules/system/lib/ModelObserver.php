<?

class ModelObserver {

    protected function clearCacheTags($tags) {
        Cache::tags($tags)->flush();
    }

    public function saved($model) {
        $this->clearCacheTags($model->getTable());
    }

    public function deleted($model) {
        $this->clearCacheTags($model->getTable());
    }

    public function restored($model) {
        $this->clearCacheTags($model->getTable());
    }

}