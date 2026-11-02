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
      title: (json['title'] ?? '').toString(),
      categoryId: (json['categoryId'] ?? json['category_name'] ?? 'c1').toString(),
      content: (json['content'] ?? json['content_text'] ?? '').toString(),
      imageUrl: json['image_url']?.toString(),
    );
  }
}
