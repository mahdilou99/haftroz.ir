class Story {
  final String id;
  final String title;
  final String categoryId;
  final String content;
  final String? imageUrl;

  const Story({
    required this.id,
    required this.title,
    required this.categoryId,
    required this.content,
    this.imageUrl,
  });

  factory Story.fromJson(Map<String, dynamic> json) {
    return Story(
      id: json['id'].toString(),
      title: json['title'] ?? '',
      categoryId: json['category_name'] ?? 'c1',
      content: json['content_text'] ?? '',
      imageUrl: json['image_url'],
    );
  }
}
